<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/tools/migrate.php';
require_once dirname(__DIR__).'/system/common/filter.php';
$db = migration_connection();
if (!$db->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) {
    exit("Refusing mutation tests outside the disposable development database.\n");
}
$passed = 0;
function expect_check($condition, $name): void {
    global $passed;
    if (!$condition) { throw new RuntimeException('FAIL: '.$name); }
    $passed++; echo 'PASS '.$name."\n";
}
function request(string $method, string $path, array &$cookies, array|string $data = [], array $extra = []): array {
    $body = is_array($data) ? http_build_query($data) : $data;
    $headers = ['Host'=>'localhost', 'Connection'=>'close'];
    if ($cookies) { $headers['Cookie'] = implode('; ', array_map(static fn($k,$v)=>$k.'='.$v,array_keys($cookies),$cookies)); }
    if ($method === 'POST') { $headers += ['Content-Type'=>'application/x-www-form-urlencoded','Content-Length'=>strlen($body)]; }
    $raw = "$method $path HTTP/1.0\r\n";
    foreach (array_merge($headers,$extra) as $k=>$v) { $raw .= "$k: $v\r\n"; }
    $raw .= "\r\n".$body;
    $socket = stream_socket_client('tcp://web:8080', $errno, $error, 10);
    if (!$socket) { throw new RuntimeException($error); }
    stream_set_timeout($socket,20);
    for ($offset=0; $offset<strlen($raw);) {
        $n = fwrite($socket,substr($raw,$offset));
        if (!$n) { throw new RuntimeException('HTTP write failed.'); } $offset += $n;
    }
    $response = stream_get_contents($socket); fclose($socket);
    [$header,$content] = explode("\r\n\r\n",$response,2);
    preg_match('~HTTP/\S+ (\d+)~',$header,$status);
    preg_match_all('/^Set-Cookie:\s*([^=;]+)=([^;]*)/mi',$header,$matches,PREG_SET_ORDER);
    foreach ($matches as $m) { $cookies[$m[1]] = $m[2]; }
    return ['status'=>(int)$status[1],'headers'=>$header,'body'=>$content,'json'=>json_decode($content,true)];
}
function token(array &$cookies, bool $admin=false): string {
    $page = request('GET',$admin?'/admin/index.php':'/admin/login.php',$cookies);
    $pattern = $admin ? '/name="csrf-token" content="([a-f0-9]+)"/' : '/name="_csrf" value="([a-f0-9]+)"/';
    if (!preg_match($pattern,$page['body'],$m)) { throw new RuntimeException('No CSRF token; HTTP '.$page['status']); }
    return $m[1];
}
function upload(array &$cookies,string $token,string $name,string $content, string $dir="image"): array {
    $b = 'fengcms'.bin2hex(random_bytes(8));
    $body = "--$b\r\nContent-Disposition: form-data; name=\"_csrf\"\r\n\r\n$token\r\n";
    $body .= "--$b\r\nContent-Disposition: form-data; name=\"imgFile\"; filename=\"$name\"\r\nContent-Type: application/octet-stream\r\n\r\n$content\r\n--$b--\r\n";
    return request('POST','/admin/api/upload.php?dir='.rawurlencode($dir),$cookies,$body,['Content-Type'=>'multipart/form-data; boundary='.$b]);
}
$tag=bin2hex(random_bytes(6)); $user='test_'.$tag; $legacyUser='legacy_'.$tag;
$password=bin2hex(random_bytes(16)); $legacyPassword=bin2hex(random_bytes(16))."'/&";
$files=[]; $directories=[]; $fixture='f_security_test_'.$tag; $testId=$legacyId=null;
$articleIds=[]; $messageId=null; $fieldFixtureId=null;
$originalBase=$db->query('SELECT name,copyright FROM f_base LIMIT 1')->fetch_assoc();
$originalName=$originalBase['name'];
$settingsPath=(getenv('APP_RUNTIME_PATH') ?: dirname(__DIR__).'/var/runtime').'/site.json';
$originalSettings=is_file($settingsPath) ? file_get_contents($settingsPath) : null;
try {
    $cookies=[];
    $page=request('GET','/',$cookies);
    expect_check($page['status']===200 && str_contains($page['body'],'<title>') && !str_contains($page['body'],'{loop '),'homepage renders');
    expect_check(request('GET','/admin/index.php',$cookies)['status']===302,'anonymous admin redirects');
    foreach (['/install/index.php','/config.php','/.env','/.git/config','/app/error/error.php','/README.md','/template/index.html','/admin/app/dbbackup/test.sql','/admin/public/htmledit/php/json.php'] as $path) {
        expect_check(request('GET',$path,$cookies)['status']===404,'private path blocked: '.$path);
    }
    expect_check(request('GET','/?controller[]=home',$cookies)['status']===400,'array route rejected');
    expect_check(request('GET','/?controller=../admin',$cookies)['status']===400,'route traversal rejected');
    expect_check(request('GET','/?controller=home&operate=display',$cookies)['status']===404,'inherited helper blocked');
    expect_check(request('GET','/?controller=search&operate=projectexist',$cookies)['status']===404,'non-action helper blocked');
    expect_check(request('GET','/admin/api/files.php',$cookies)['status']===401,'anonymous file manager blocked');
    expect_check(upload($cookies,'','test.png','invalid')['status']===401,'anonymous upload blocked');
    expect_check(request('POST','/admin/index.php?controller=admin',$cookies,['user'=>$user])['status']===403,'login requires CSRF');
    $csrf=token($cookies);
    $page=request('GET','/admin/login.php',$cookies);
    expect_check(!str_contains($page['body'],'cnzz.com') && str_contains(strtolower($page['headers']),'cache-control: no-store'),'login has no tracker and is not cached');
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $stmt=$db->prepare('INSERT INTO f_manage (admin,password,datetime) VALUES (?,?,NOW())');
    $stmt->bind_param('ss',$user,$hash); $stmt->execute(); $testId=$db->insert_id;
    $legacyHash=md5(md5(md5(lib_replace_end_tag($legacyPassword))));
    $stmt->bind_param('ss',$legacyUser,$legacyHash); $stmt->execute(); $legacyId=$db->insert_id;
    $login=['_csrf'=>$csrf,'user'=>$user,'password'=>$password,'admincode'=>getenv('ADMIN_CODE')];
    $bad=$login; $bad['user']='admin" OR 1=1 #';
    expect_check((request('POST','/admin/index.php?controller=admin',$cookies,$bad)['json']['status']??'')==='n','login SQL injection rejected');
    $sid=$cookies['fengcms_session'];
    expect_check((request('POST','/admin/index.php?controller=admin',$cookies,$login)['json']['status']??'')==='y','modern login succeeds');
    expect_check($sid!==$cookies['fengcms_session'],'session ID rotates');
    $newToken=token($cookies,true); expect_check($newToken!==$csrf,'CSRF rotates');
    expect_check(request('GET','/admin/index.php?controller=module&project=manage',$cookies)['status']===404,'content editor cannot address administrator table');
    $articleTitle='probe_'.$tag.' <b data-probe="title">';
    $rich='<p>安全回归正文 '.$tag.'</p>';
    $articleStmt=$db->prepare('INSERT INTO f_article (title,content,info,template,classid,status,time,date) VALUES (?,?,?,"/article_content.html","0",?,UNIX_TIMESTAMP(),CURDATE())');
    $status=1; $articleStmt->bind_param('sssi',$articleTitle,$rich,$tag,$status); $articleStmt->execute(); $articleId=$db->insert_id; $articleIds[]=$articleId;
    $status=0; $articleStmt->execute(); $draftId=$db->insert_id; $articleIds[]=$draftId;
    $public=request('GET','/?controller=content&project=article&id='.$articleId,$cookies);
    expect_check($public['status']===200 && str_contains($public['body'],'&lt;b data-probe=&quot;title&quot;&gt;') && !str_contains($public['body'],'<b data-probe="title">'),'article title is escaped');
    expect_check(str_contains($public['body'],$rich),'legacy content variable retains rich markup');
    expect_check(str_contains($public['body'],'&gt;&gt;') && !str_contains($public['body'],'&amp;gt;'),
        'breadcrumb separator is HTML-escaped exactly once');

    expect_check(request('GET','/?controller=content&project=article&id='.$draftId,$cookies)['status']===404,'unpublished article is private');
    $query='/index.php?controller=search&tags='.rawurlencode('<b data-probe="search">');
    $search=request('GET',$query,$cookies);
    expect_check($search['status']===200 && str_contains($search['body'],'&lt;b data-probe=&quot;search&quot;&gt;') && !str_contains($search['body'],'<b data-probe="search">'),'search reflects escaped text');
    $query='/index.php?controller=search&project=article&tags='.rawurlencode('" OR 1=1 #');
    $search=request('GET',$query,$cookies);
    expect_check($search['status']===200 && !str_contains($search['body'],$tag),'search SQL injection cannot return unrelated articles');
    $edit=request('POST','/admin/index.php?controller=module&project=article&operate=save',$cookies,['_csrf'=>$newToken,'id'=>$articleId,'title'=>'updated_'.$tag,'info'=>'updated','content'=>$rich]);
    expect_check(($edit['json']['status']??'')==='y','article edit succeeds with CSRF');
    expect_check($db->query('SELECT title FROM f_article WHERE id='.(int)$articleId)->fetch_assoc()['title']==='updated_'.$tag,'article edit persists');
    $attack='<p onclick="alert(1)">安全<strong>加粗</strong><script>alert(1)</script><iframe src="https://example.com"></iframe><a href="javascript:alert(1)">链接</a><img src="/upload/safe.png" onerror="alert(1)"></p>';
    $cleanSave=request('POST','/admin/index.php?controller=module&project=article&operate=save',$cookies,['_csrf'=>$newToken,'id'=>$articleId,'title'=>'updated_'.$tag,'content'=>$attack]);
    $cleaned=$db->query('SELECT content FROM f_article WHERE id='.(int)$articleId)->fetch_assoc()['content'];
    expect_check(($cleanSave['json']['status']??'')==='y' && !preg_match('/<script|<iframe|onerror|onclick|javascript:/i',$cleaned), 'article save sanitizes HTML on the server');
    expect_check(str_contains($cleaned,'<strong>加粗</strong>') && str_contains($cleaned,'src="/upload/safe.png"'), 'sanitizer preserves normal formatting and relative images');
    $cleanPublic=request('GET','/?controller=content&project=article&id='.$articleId,$cookies);
    expect_check(str_contains($cleanPublic['body'],$cleaned), 'sanitized rich text still renders with legacy template syntax');
    $networkHTML='<p>网络图片<img src="https://images.example.com/photo?id=1&amp;size=large" alt="网络图片"><img src="http://images.example.com/plain.jpg" alt="普通网址"></p>';
    $networkSave=request('POST','/admin/index.php?controller=module&project=article&operate=save',$cookies,['_csrf'=>$newToken,'id'=>$articleId,'title'=>'updated_'.$tag,'content'=>$networkHTML]);
    $networkSaved=$db->query('SELECT content FROM f_article WHERE id='.(int)$articleId)->fetch_assoc()['content'];
    expect_check(($networkSave['json']['status']??'')==='y' && str_contains($networkSaved,'https://images.example.com/photo?id=1&amp;size=large') && str_contains($networkSaved,'http://images.example.com/plain.jpg'), 'network images survive server sanitization and storage');
    $networkEdit=request('GET','/admin/index.php?controller=module&project=article&operate=update&id='.$articleId,$cookies);
    expect_check($networkEdit['status']===200 && str_contains($networkEdit['body'],'https://images.example.com/photo?id=1&amp;amp;size=large'), 'network image URL is escaped for editing without losing query parameters');
    $networkPublic=request('GET','/?controller=content&project=article&id='.$articleId,$cookies);
    expect_check($networkPublic['status']===200 && str_contains($networkPublic['body'],$networkSaved), 'network images render through the original frontend template');

    $base=request('POST','/admin/index.php?controller=base&operate=save',$cookies,['_csrf'=>$newToken,'name'=>'base_'.$tag,'url_type'=>'1']);
    expect_check(($base['json']['status']??'')==='y','site settings save without writing PHP source');
    $copyrightSave=request('POST','/admin/index.php?controller=base&operate=save',$cookies,['_csrf'=>$newToken,'copyright'=>$attack,'url_type'=>'1']);
    $copyright=$db->query('SELECT copyright FROM f_base LIMIT 1')->fetch_assoc()['copyright'];
    expect_check(($copyrightSave['json']['status']??'')==='y' && !str_contains($copyright,'<script') && str_contains($copyright,'<strong>加粗</strong>'), 'copyright HTML uses the server sanitizer');

    expect_check(request('GET','/index.html',$cookies)['status']===200,'rewrite mode renders homepage');
    request('POST','/admin/index.php?controller=base&operate=save',$cookies,['_csrf'=>$newToken,'name'=>$originalName,'url_type'=>'0']);
    foreach (['home&operate=main','base','manage&operate=password','modulemanage','module&project=article','classify','message','tplmanage','dbmanage&operate=dbmanages'] as $route) {
        $page=request('GET','/admin/index.php?controller='.$route,$cookies);
        expect_check($page['status']===200 && str_contains($page['body'],'<html'),'admin screen renders: '.$route);
    }
    foreach (['add', 'update&id='.$articleId] as $operation) {
        $formPage=request('GET','/admin/index.php?controller=module&project=article&operate='.$operation,$cookies);
        preg_match('/<form\b[^>]*>/i', $formPage['body'], $formTag);
        expect_check(str_contains($formPage['body'],'/admin/public/wangeditor/index.js') && str_contains($formPage['body'],'textarea data-cms-editor') && !str_contains($formPage['body'],'kindeditor.js'), 'article screen loads wangEditor adapter: '.$operation);

        expect_check($formPage['status']===200 && isset($formTag[0]) &&
            str_contains($formTag[0], 'action="?controller=module&project=article&operate=save"') &&
            !str_contains($formTag[0], '<input'), 'article form action intact: '.$operation);
        expect_check((bool)preg_match('/<form\b[^>]*>\s*<input type="hidden" name="_csrf" value="'.$newToken.'">/i', $formPage['body']),
            'article CSRF field is inside form: '.$operation);
    }
    $delete='/admin/index.php?controller=module&operate=deleteone&project=article&id=0';
    expect_check(request('GET',$delete,$cookies)['status']===405,'GET cannot delete');
    expect_check(request('POST',$delete,$cookies)['status']===403,'authenticated writes require CSRF');
    expect_check(request('POST','/admin/index.php?controller=manage&operate=quit',$cookies,['_csrf'=>$csrf])['status']===403,'stale CSRF rejected');
    expect_check(request('POST','/admin/index.php?controller=module&operate=batch&project=article',$cookies,['_csrf'=>$newToken,'method'=>'display'])['status']===400,'batch method injection rejected');
    expect_check(upload($cookies,'','test.png','x')['status']===403,'upload requires CSRF');
    expect_check(upload($cookies,$newToken,'test.php','<?php echo 1;')['status']===400,'executable upload rejected');
    expect_check(upload($cookies,$newToken,'test.png','<?php echo 1;')['status']===400,'spoofed image rejected');
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aQ1cAAAAASUVORK5CYII=');
    $uploaded=upload($cookies,$newToken,'pixel.png',$png);
    expect_check($uploaded['status']===200 && ($uploaded['json']['error']??-1)===0,'authenticated image upload succeeds');
    $url=$uploaded['json']['url']; $files[]=dirname(__DIR__).$url;
    expect_check(request('GET',$url,$cookies)['body']===$png,'uploaded image is served');
    expect_check(request('GET','/?controller=down&file='.rawurlencode(base64_encode($url)),$cookies)['body']===$png,'download preserves upload bytes');
    expect_check(request('GET','/?controller=down&file='.rawurlencode(base64_encode('/upload/../config.php')),$cookies)['status']===400,'download traversal rejected');
    // A valid GIF comment can contain any bytes, including old script markers.
    $gif=base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    $comment="binary <% <?= <?php marker";
    $gif=substr($gif,0,-1)."\x21\xfe".chr(strlen($comment)).$comment."\x00\x3b";
    $binaryUpload=upload($cookies,$newToken,'binary.gif',$gif);
    expect_check($binaryUpload['status']===200 && ($binaryUpload['json']['error']??-1)===0,
        'valid image containing script-like bytes is accepted');
    $binaryUrl=$binaryUpload['json']['url']; $files[]=dirname(__DIR__).$binaryUrl;
    expect_check(request('GET',$binaryUrl,$cookies)['body']===$gif, 'binary image is served unchanged as static data');
    $attachment=upload($cookies,$newToken,'notes.txt','attachment '.$tag,'file');
    expect_check($attachment['status']===200 && ($attachment['json']['error']??-1)===0, 'authenticated attachment upload succeeds');
    $files[]=dirname(__DIR__).$attachment['json']['url'];
    expect_check(upload($cookies,$newToken,'bad.html','<script>alert(1)</script>','file')['status']===400, 'HTML attachment rejected');
    expect_check(upload($cookies,$newToken,'bad.svg','<svg onload="alert(1)"></svg>')['status']===400, 'SVG upload rejected');
    expect_check(request('GET','/admin/public/htmledit/php/upload_json.php',$cookies)['status']===404, 'old editor upload endpoint disabled');
    $manager='/admin/api/files.php';
    expect_check(isset(request('GET',$manager,$cookies)['json']['file_list']),'template picker returns JSON');
    expect_check(request('GET',$manager.'?path=../',$cookies)['status']===400,'file manager traversal rejected');
    foreach (['page[]=1','page=0','limit=1000','order[]=name','direction=unknown','q[]=x'] as $invalid) {
        expect_check(request('GET',$manager.'?'.$invalid,$cookies)['status']===400,'resource query validation: '.$invalid);
    }
    $resourceDir=dirname(__DIR__).'/upload/image/qa_'.$tag;
    mkdir($resourceDir); $directories[]=$resourceDir;
    $canvas=imagecreatetruecolor(640,480); imagefill($canvas,0,0,imagecolorallocate($canvas,40,90,150));
    foreach (['alpha.png','beta.png','gamma.png','quote"name.png'] as $imageName) {
        imagepng($canvas,$resourceDir.'/'.$imageName);$files[]=$resourceDir.'/'.$imageName;
    }
    imagedestroy($canvas);
    $baseQuery=$manager.'?dir=image&path=qa_'.$tag.'/&order=name&direction=asc&limit=2';
    $first=request('GET',$baseQuery,$cookies)['json'];$second=request('GET',$baseQuery.'&page=2',$cookies)['json'];
    expect_check($first['total_count']===4 && $first['page_count']===2 && count($first['file_list'])===2 && $second['page']===2,'resource listing paginates on the server');
    expect_check($first['file_list'][0]['filename']==='alpha.png' && $second['file_list'][0]['filename']==='gamma.png','resource ordering is stable across pages');
    $filtered=request('GET',$baseQuery.'&q=beta',$cookies)['json'];
    expect_check($filtered['total_count']===1 && $filtered['file_list'][0]['filename']==='beta.png','resource search filters current directory');
    expect_check($first['file_list'][0]['width']===640 && $first['file_list'][0]['height']===480,'image metadata is returned for current page');
    $quoted=request('GET',$baseQuery.'&q=quote',$cookies)['json']['file_list'][0];
    expect_check(str_contains($quoted['url'],'%22') && $quoted['filename']==='quote"name.png','resource URLs encode special file names');
    $thumbUrl=$first['file_list'][0]['thumbnail_url'];$anonymous=[];
    expect_check(request('GET',$thumbUrl,$anonymous)['status']===401,'thumbnail requires authentication');
    $thumb=request('GET',$thumbUrl,$cookies);$thumbSize=getimagesizefromstring($thumb['body']);
    expect_check($thumb['status']===200 && $thumbSize[0]===320 && $thumbSize[1]===240 && str_contains(strtolower($thumb['headers']),'content-type: image/png'),'thumbnail resizes image within bounds');
    expect_check(request('GET','/admin/api/thumbnail.php?path=../config.php',$cookies)['status']===400,'thumbnail path traversal rejected');
    expect_check(request('GET','/admin/api/thumbnail.php?path[]=a',$cookies)['status']===400,'thumbnail array path rejected');

    expect_check(request('GET','/admin/index.php?controller=tplmanage&operate=update&project=tpl&name=../../config',$cookies)['status']===400,'template editor traversal rejected');
    expect_check(request('POST','/admin/index.php?controller=dbmanage&operate=delete&name=..',$cookies,['_csrf'=>$newToken])['status']===400,'backup deletion traversal rejected');
    $executable=dirname(__DIR__).'/upload/test_'.$tag.'.php'; file_put_contents($executable,'<?php echo "executed";'); $files[]=$executable;
    expect_check(request('GET','/upload/'.basename($executable),$cookies)['status']===404,'uploaded PHP cannot execute');
    define('TPL_INCLUDE',1); define('ABS_PATH',dirname(__DIR__)); define('ROOT_PATH',ABS_PATH);
    $_SERVER+=['HTTP_HOST'=>'localhost','PHP_SELF'=>'/index.php','REQUEST_URI'=>'/'];
    require ROOT_PATH.'/system/app.php';
    foreach ([
        '<svg><script>alert(1)</script></svg>',
        '<img src="data:image/svg+xml,evil" onerror="alert(1)">',
        '<a href="java&#x73;cript:alert(1)">bad</a>',
        '<p style="background-image:url(javascript:alert(1));position:fixed">text</p>'
    ] as $hostile) {
        expect_check(!preg_match('/<script|<svg|onerror|javascript:|data:image|position:|background-image/i',cms_clean_html($hostile)), 'HTML sanitizer blocks hostile markup');
    }
    $tableHtml='<table><tbody><tr><td colspan="2">表格</td></tr></tbody></table>';
    expect_check(str_contains(cms_clean_html($tableHtml),'<td colspan="2">表格</td>'), 'HTML sanitizer preserves table cells');
    $customName='qa_rich_'.$tag;
    $fieldInsert=$db->prepare('INSERT INTO f_module_field (module_id,name,aliases,type,status) SELECT m.id,?,"QA rich text",t.id,1 FROM f_module m JOIN f_module_field_type t ON t.quote="htmledit.html" WHERE m.project="article" LIMIT 1');
    $fieldInsert->bind_param('s',$customName); $fieldInsert->execute(); $fieldFixtureId=$db->insert_id;
    $customClean=cms_clean_record([$customName=>$attack],'article');
    expect_check($fieldFixtureId>0 && !str_contains($customClean[$customName],'<script') && str_contains($customClean[$customName],'<strong>加粗</strong>'), 'custom rich-text fields use metadata-driven sanitization');
    $metadataFixture=['content'=>$attack,'title'=>'<b>plain title</b>'];
    $metadataClean=cms_clean_record($metadataFixture,'article');
    expect_check(!str_contains($metadataClean['content'],'<script') && $metadataClean['title']===$metadataFixture['title'], 'rich text policy follows field metadata without changing text fields');
    $pagination=arraypage(range(1,25),10);
    expect_check($pagination['fy'] instanceof CmsHtml && str_contains(cms_template_output($pagination['fy']),'<a '),
        'legacy pagination value renders trusted links without raw syntax');
    $untrusted=['fy'=>'<script>alert(1)</script>','content'=>'<img src=x onerror=alert(1)>'];
    expect_check(str_contains(cms_template_output($untrusted['fy']),'&lt;script&gt;') &&
        str_contains(cms_template_output($untrusted['content']),'&lt;img'), 'HTML trust is not inferred from variable names');
    expect_check(cms_template_output(cms_html('<p>trusted</p>'))==='<p>trusted</p>', 'explicit renderer HTML retains markup');
    foreach (['template','admin/template'] as $directory) {
        $legacySyntax=true;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_PATH.'/'.$directory)) as $file) {
            if ($file->isFile() && $file->getExtension()==='html' && str_contains(file_get_contents($file->getPathname()),'{raw ')) { $legacySyntax=false; }
        }
        expect_check($legacySyntax,'bundled templates retain original variable syntax: '.$directory);
    }

    $_SESSION['authnum']='abcd';
    $message=M('message')->save(['vcode'=>'abcd','title'=>'message_'.$tag,'content'=>'<b data-probe="message">hello</b>','name'=>'visitor','status'=>'1','reply'=>'injected','time'=>'1']);
    $messageId=$message['id']??null;
    $savedMessage=$db->query('SELECT * FROM f_message WHERE id='.(int)$messageId)->fetch_assoc();
    expect_check(($message['status']??'')==='y' && (int)$savedMessage['status']===0 && $savedMessage['reply']===null && (int)$savedMessage['time']>1,'public message cannot set publication, reply or time');
    $messagePage=request('GET','/admin/index.php?controller=message',$cookies);
    expect_check(str_contains($messagePage['body'],'&lt;b data-probe=&quot;message&quot;&gt;') && !str_contains($messagePage['body'],'<b data-probe="message">'),'visitor message is escaped in admin');
    $db->query("CREATE TABLE `$fixture` (id int AUTO_INCREMENT PRIMARY KEY,name varchar(255),note text) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $text="单引号' 双引号\" 反斜杠\\ 1e10 ; DROP TABLE test";
    $id=D(substr($fixture,2))->insert(['name'=>$text,'note'=>null]);
    $row=D(substr($fixture,2))->where(['name'=>$text])->getone();
    expect_check($row && $row['name']===$text && $row['note']===null,'prepared insert/where preserve quotes, Unicode and NULL');
    D(substr($fixture,2))->where(['id'=>$id])->update(['note'=>$text]);
    expect_check(D(substr($fixture,2))->where(['id'=>$id])->getone()['note']===$text,'prepared update binds values and conditions');
    $rejected=false; try { D('manage;DROP'); } catch (InvalidArgumentException $e) { $rejected=true; }
    expect_check($rejected,'SQL table identifier injection rejected');
    $legacyCookies=[]; $legacyToken=token($legacyCookies);
    $legacyLogin=['_csrf'=>$legacyToken,'user'=>$legacyUser,'password'=>$legacyPassword,'admincode'=>getenv('ADMIN_CODE')];
    expect_check((request('POST','/admin/index.php?controller=admin',$legacyCookies,$legacyLogin)['json']['status']??'')==='y','legacy triple-MD5 login succeeds');
    $row=$db->query('SELECT password FROM f_manage WHERE id='.(int)$legacyId)->fetch_assoc();
    expect_check(strlen($row['password'])>32 && password_verify($legacyPassword,$row['password']),'legacy hash upgrades');
    $newPassword=bin2hex(random_bytes(16));
    $change=request('POST','/admin/index.php?controller=manage&operate=save',$cookies,['_csrf'=>$newToken,'user'=>$user,'password'=>$password,'newpassword'=>$newPassword,'newpasswords'=>$newPassword]);
    expect_check(($change['json']['status']??'')==='y','administrator changes own password');
    $own=$db->query('SELECT password FROM f_manage WHERE id='.(int)$testId)->fetch_assoc();
    $other=$db->query('SELECT password FROM f_manage WHERE id='.(int)$legacyId)->fetch_assoc();
    expect_check(password_verify($newPassword,$own['password']) && password_verify($legacyPassword,$other['password']),'password change leaves other accounts intact');
    expect_check(request('GET','/admin/index.php',$cookies)['status']===302,'password change logs out');
    $rateCookies=[]; $rateToken=token($rateCookies);
    for ($i=0;$i<11;$i++) {
        $rate=request('POST','/admin/index.php?controller=admin',$rateCookies,['_csrf'=>$rateToken,'user'=>'absent_'.$tag,'password'=>'incorrect','admincode'=>getenv('ADMIN_CODE')]);
    }
    expect_check($rate['status']===429,'login rate limit enforced');
    echo "All $passed regression checks passed.\n";
} finally {
    foreach ($files as $file) { if (is_file($file)) { unlink($file); } }
    foreach (array_reverse($directories) as $directory) { if (is_dir($directory)) { rmdir($directory); } }
    foreach ([$testId,$legacyId] as $id) { if ($id) { $db->query('DELETE FROM f_manage WHERE id='.(int)$id); } }
    foreach ($articleIds as $id) { $db->query('DELETE FROM f_article WHERE id='.(int)$id); }
    if ($messageId) { $db->query('DELETE FROM f_message WHERE id='.(int)$messageId); }
    if ($fieldFixtureId) { $db->query('DELETE FROM f_module_field WHERE id='.(int)$fieldFixtureId); }
    $restore=$db->prepare('UPDATE f_base SET name=?,copyright=?'); $restore->bind_param('ss',$originalName,$originalBase['copyright']); $restore->execute();
    if ($originalSettings===null) { if (is_file($settingsPath)) { unlink($settingsPath); } }
    else { file_put_contents($settingsPath,$originalSettings); }
    foreach ([$user,$legacyUser,'absent_'.$tag,'admin" OR 1=1 #'] as $name) {
        $bucket=hash_hmac('sha256','user:'.mb_strtolower($name,'UTF-8'),getenv('APP_KEY'));
        $stmt=$db->prepare('DELETE FROM f_login_attempts WHERE bucket=?'); $stmt->bind_param('s',$bucket); $stmt->execute();
    }
    $bucket=hash_hmac('sha256','ip:'.gethostbyname('web'),getenv('APP_KEY'));
    $stmt=$db->prepare('DELETE FROM f_login_attempts WHERE bucket=?'); $stmt->bind_param('s',$bucket); $stmt->execute();
    $db->query("DROP TABLE IF EXISTS `$fixture`");
}
