<?php
require_once 'config.php';
if (empty($_SESSION['sitak_admin'])) { http_response_code(403); exit('دسترسی غیرمجاز'); }

function sitak_backup_clean_output(){ while (ob_get_level() > 0) { @ob_end_clean(); } }
class SitakZipStream {
    private $offset = 0;
    private $entries = [];
    private $cur = null;
    private function out($s) { echo $s; $this->offset += strlen($s); }
    private function dosTime($ts) {
        $d = getdate($ts);
        return [($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2), (($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday']];
    }
    // ZIP بدون فشرده‌سازی (store) با data descriptor: چون طول و CRC قبل از ارسال معلوم نیست، بعد از داده نوشته می‌شوند.
    public function begin($name, $isDir = false, $mtime = null) {
        [$t, $dt] = $this->dosTime($mtime ?: time());
        $name = str_replace('\\', '/', $name);
        $flags = $isDir ? 0x0800 : 0x0808;
        $this->cur = ['name' => $name, 'dir' => $isDir, 'off' => $this->offset, 't' => $t, 'dt' => $dt, 'flags' => $flags, 'size' => 0, 'hash' => null];
        $this->out(pack('VvvvvvVVVvv', 0x04034b50, 20, $flags, 0, $t, $dt, 0, 0, 0, strlen($name), 0) . $name);
        if (!$isDir) $this->cur['hash'] = hash_init('crc32b');
    }
    public function chunk($data) {
        if ($data === '' || $data === null || $this->cur === null) return;
        if ($this->cur['hash']) hash_update($this->cur['hash'], $data);
        $this->cur['size'] += strlen($data);
        $this->out($data);
        flush();
    }
    public function end() {
        $c = $this->cur; $crc = 0;
        if (!$c['dir']) {
            $crc = unpack('N', hash_final($c['hash'], true))[1];
            $this->out(pack('VVVV', 0x08074b50, $crc, $c['size'], $c['size']));
        }
        $this->entries[] = ['name' => $c['name'], 'dir' => $c['dir'], 'off' => $c['off'], 't' => $c['t'], 'dt' => $c['dt'], 'flags' => $c['flags'], 'crc' => $crc, 'size' => $c['size']];
        $this->cur = null;
    }
    public function file($name, $path) {
        $fh = @fopen($path, 'rb');
        if (!$fh) return;
        $this->begin($name, false, @filemtime($path));
        while (!feof($fh)) {
            $buf = fread($fh, 65536);
            if ($buf === false) break;
            $this->chunk($buf);
        }
        fclose($fh);
        $this->end();
    }
    public function dir($name, $mtime = null) {
        $this->begin(rtrim($name, '/') . '/', true, $mtime);
        $this->end();
    }
    public function finish() {
        $cdStart = $this->offset;
        foreach ($this->entries as $e) {
            $attr = $e['dir'] ? ((0x41ED << 16) | 0x10) : (0x81A4 << 16);
            $fmt = 'V' . str_repeat('v', 6) . 'VVV' . str_repeat('v', 5) . 'VV';
            $this->out(pack($fmt, 0x02014b50, 20, 20, $e['flags'], 0, $e['t'], $e['dt'], $e['crc'], $e['size'], $e['size'], strlen($e['name']), 0, 0, 0, 0, $attr, $e['off']) . $e['name']);
        }
        $n = count($this->entries);
        $this->out(pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, $this->offset - $cdStart, $cdStart, 0));
        flush();
    }
}
// دامپ دیتابیس به‌صورت استریم: ردیف‌ها بدون بافر کامل خوانده و تکه‌تکه ارسال می‌شوند.
function sitak_backup_sql_stream($conn, callable $emit) {
    $buf = ''; $flush = function () use (&$buf, $emit) { if ($buf !== '') { $emit($buf); $buf = ''; } };
    $buf .= "-- SiTak database backup\n-- Generated: ".date('c')."\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = [];
    $r = $conn->query('SHOW TABLES');
    while ($r && ($row = $r->fetch_array(MYSQLI_NUM))) $tables[] = $row[0];
    foreach ($tables as $table) {
        $q = $conn->query("SHOW CREATE TABLE `".$conn->real_escape_string($table)."`");
        $create = $q ? $q->fetch_assoc() : null;
        $createSql = $create['Create Table'] ?? ($create['Create View'] ?? '');
        $buf .= "DROP TABLE IF EXISTS `".$table."`;\n".$createSql.";\n\n";
        $rows = $conn->query("SELECT * FROM `".$conn->real_escape_string($table)."`", MYSQLI_USE_RESULT);
        if ($rows) {
            $any = false;
            while ($row = $rows->fetch_assoc()) {
                $any = true; $vals = [];
                foreach ($row as $v) $vals[] = $v === null ? 'NULL' : "'".$conn->real_escape_string((string)$v)."'";
                $buf .= "INSERT INTO `".$table."` VALUES (".implode(',', $vals).");\n";
                if (strlen($buf) >= 1048576) $flush();
            }
            $rows->free();
            if ($any) $buf .= "\n";
        }
        $flush();
    }
    $buf .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $flush();
}
function sitak_backup_zip_tree($zip, $dir, $base) {
    $items = @scandir($dir) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'backup-temp') continue;
        $path = $dir.DIRECTORY_SEPARATOR.$item; $local = $base.$item;
        if (is_dir($path)) { $zip->dir($local.'/', @filemtime($path)); sitak_backup_zip_tree($zip, $path, $local.'/'); }
        elseif (is_file($path)) $zip->file($local, $path);
    }
}
function sitak_backup_extract($zipFile,$target){
    $zip=new ZipArchive(); if($zip->open($zipFile)!==true) throw new Exception('فایل ZIP معتبر نیست.');
    if(!$zip->extractTo($target)){ $zip->close(); throw new Exception('استخراج فایل پشتیبان انجام نشد.'); }
    $zip->close();
}
function sitak_backup_copy_tree($from,$to){
    $items=@scandir($from)?:[]; foreach($items as $item){if($item==='.'||$item==='..')continue;$src=$from.'/'.$item;$dst=$to.'/'.$item;if(is_dir($src)){if(!is_dir($dst))@mkdir($dst,0755,true);sitak_backup_copy_tree($src,$dst);}elseif(is_file($src)){@copy($src,$dst);}}
}
function sitak_backup_sql_statements($sql){
    $sql=preg_replace('/^--.*$/m','',$sql); $parts=[]; $buf=''; $quote=''; $len=strlen($sql);
    for($i=0;$i<$len;$i++){ $c=$sql[$i]; $buf.=$c; if($quote){ if($c==='\\' && $i+1<$len){$buf.=$sql[++$i];continue;} if($c===$quote)$quote=''; } elseif($c==="'"||$c==='"'||$c==='`'){$quote=$c;} elseif($c===';'){ $stmt=trim($buf); if($stmt!=='')$parts[]=$stmt; $buf=''; } }
    if(trim($buf)!=='')$parts[]=trim($buf); return $parts;
}
function sitak_backup_restore_sql($conn,$sql){ foreach(sitak_backup_sql_statements($sql) as $stmt){ if(!$conn->query($stmt)) throw new Exception('خطا در بازگردانی دیتابیس: '.$conn->error); } }

if (isset($_GET['download'])) {
    if (!sitak_csrf_valid($_GET['csrf'] ?? '')) { http_response_code(403); exit('درخواست نامعتبر است.'); }
    $type=$_GET['download']; $stamp=date('Ymd_His'); sitak_backup_clean_output();
    if(!in_array($type,['database','files','full'],true)){http_response_code(400);exit('نوع پشتیبان نامعتبر است.');}
    if (function_exists('set_time_limit')) @set_time_limit(0);
    header('X-Accel-Buffering: no'); header('Cache-Control: no-store');
    if($type==='database'){
        header('Content-Type: application/sql; charset=utf-8'); header('Content-Disposition: attachment; filename="database-'.$stamp.'.sql"');
        sitak_backup_sql_stream($conn, function ($s) { echo $s; flush(); }); exit;
    }
    header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="'.$type.'-'.$stamp.'.zip"');
    $zip = new SitakZipStream();
    if($type==='full'){
        $zip->begin('database.sql'); sitak_backup_sql_stream($conn, function ($s) use ($zip) { $zip->chunk($s); }); $zip->end();
        $zip->dir('site-files/');
        sitak_backup_zip_tree($zip, __DIR__, 'site-files/');
    } else {
        sitak_backup_zip_tree($zip, __DIR__, '');
    }
    $zip->finish(); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['restore_type'])) {
    if(!sitak_csrf_valid($_POST['csrf_token']??'')){http_response_code(403);exit('درخواست نامعتبر است.');}
    $type=$_POST['restore_type']; $file=$_FILES['backup_file']??null;
    try{
        if(!$file || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new Exception('فایل پشتیبان انتخاب نشده است.');
        if($type==='database'){
            sitak_backup_restore_sql($conn,file_get_contents($file['tmp_name']));
        } elseif(in_array($type,['files','full'],true)) {
            $tmp=sys_get_temp_dir().'/sitak_restore_'.bin2hex(random_bytes(6)); @mkdir($tmp,0755,true); sitak_backup_extract($file['tmp_name'],$tmp);
            $root=$type==='full'&&is_dir($tmp.'/site-files')?$tmp.'/site-files':$tmp;
            if($type==='full' && is_file($tmp.'/database.sql')) sitak_backup_restore_sql($conn,file_get_contents($tmp.'/database.sql'));
            sitak_backup_copy_tree($root,__DIR__); 
            $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());} @rmdir($tmp);
        } else throw new Exception('نوع بازگردانی نامعتبر است.');
        header('Location: '.($_POST['return_to']??'admin-settings.php').'?backup=success'); exit;
    }catch(Throwable $e){ header('Location: '.($_POST['return_to']??'admin-settings.php').'?backup_error='.rawurlencode($e->getMessage())); exit; }
}
http_response_code(400); echo 'درخواست نامعتبر است.';
