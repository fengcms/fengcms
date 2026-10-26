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
$files=[]; $directories=[]; $fixture='f_security_test_'.$tag; $testId=$legacyId=null; $backupId=null;
$articleIds=[]; $messageId=null; $fieldFixtureId=null;
$categoryIds=[];
$moduleProject='qa_mod_'.$tag; $moduleFixtureId=null;
$importProject='qa_copy_'.$tag; $importFixtureId=null;
$originalBase=$db->query('SELECT name,copyright FROM f_base LIMIT 1')->fetch_assoc();
$originalName=$originalBase['name'];
$settingsPath=(getenv('APP_RUNTIME_PATH') ?: dirname(__DIR__).'/var/runtime').'/site.json';
$originalSettings=is_file($settingsPath) ? file_get_contents($settingsPath) : null;
try {
    $cookies=[];
    $page=request('GET','/',$cookies);
    expect_check($page['status']===200 && str_contains($page['body'],'<title>') && !str_contains($page['body'],'{loop '),'homepage renders');
    $tagsPage=request('GET','/tags.html',$cookies);
    expect_check($tagsPage['status']===200 && str_contains($tagsPage['body'],'id="tags_list"'),'tag cloud pseudo-static URL renders its controller');
    expect_check(str_contains(request('GET','/tags.html?source=regression',$cookies)['body'],'id="tags_list"'),'tag cloud accepts ordinary query parameters');
    expect_check(str_contains(request('GET','/?controller=tags',$cookies)['body'],'id="tags_list"'),'dynamic tag cloud URL remains available');
    $tagSearch=request('GET','/tags/security-test.html',$cookies);
    expect_check($tagSearch['status']===200 && !str_contains($tagSearch['body'],'id="tags_list"') && str_contains($tagSearch['body'],'security-test'),'tag keyword search remains separate from the tag cloud');
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
    $licensePage=request('GET','/admin/index.php?controller=home&operate=main',$cookies);
    expect_check($licensePage['status']===200 && str_contains($licensePage['body'],'MIT License') &&
        !str_contains($licensePage['body'],'非商业运用') && !str_contains($licensePage['body'],'必须在FengCms官方论坛首发'),
        'admin home displays the current MIT terms without obsolete restrictions');
    $currentVersion=trim(file_get_contents(__DIR__.'/../admin/version.php'));
    expect_check(str_contains($licensePage['body'],'id="current-version">'.htmlspecialchars($currentVersion,ENT_QUOTES,'UTF-8')) &&
        str_contains($licensePage['body'],'js/version-check.js') &&
        str_contains($licensePage['body'],'https://github.com/fengcms/fengcms/releases') &&
        !str_contains($licensePage['body'],'http://www.fengcms.com'),
        'admin home shows the GitHub release checker and current version');
    expect_check(request('GET','/admin/index.php?controller=safe&operate=serverversion',$cookies)['status']===404,
        'obsolete remote version endpoint is disabled');
    expect_check(migrate_license_notice($db,'f_')===0,'legacy sample footer migration is idempotent after update');
    $legacyFooter='Copyright 20013-2015 Powered by FengCms.COM，地方网络工作室 All Rights Reserved.<br />地方网络工作室 版权所有 未经许可严禁复制或建立镜像 <br />版本号：FengCms Beta 1.0';
    $stmt=$db->prepare('UPDATE f_base SET copyright=?');
    $stmt->bind_param('s',$legacyFooter); $stmt->execute();
    expect_check(migrate_license_notice($db,'f_')===1 &&
        str_contains($db->query('SELECT copyright FROM f_base LIMIT 1')->fetch_row()[0],'MIT License'),
        'legacy sample footer migrates to MIT notice');
    $db->query("UPDATE f_base SET copyright='QA custom site copyright'");
    expect_check(migrate_license_notice($db,'f_')===0 &&
        $db->query('SELECT copyright FROM f_base LIMIT 1')->fetch_row()[0]==='QA custom site copyright',
        'license footer migration preserves a customized site footer');
    $stmt=$db->prepare('UPDATE f_base SET copyright=?');
    $stmt->bind_param('s',$originalBase['copyright']); $stmt->execute();
    $createdModule=request('POST','/admin/index.php?controller=modulemanage&operate=save',$cookies,['_csrf'=>$newToken,'project'=>$moduleProject,'name'=>'回归模型','item'=>'商品','unit'=>'件']);
    $moduleRow=$db->query("SELECT id FROM f_module WHERE project='$moduleProject'")->fetch_assoc();
    $moduleFixtureId=$moduleRow['id']??null;
    expect_check(($createdModule['json']['status']??'')==='y' && $moduleFixtureId,'custom module can be created');
    $moduleEdit=request('POST','/admin/index.php?controller=modulemanage&operate=save',$cookies,
        ['_csrf'=>$newToken,'id'=>(string)$moduleFixtureId,'name'=>'回归模型已修改','item'=>'商品','unit'=>'件','status'=>'1']);
    expect_check(($moduleEdit['json']['status']??'')==='y' &&
        $db->query('SELECT name FROM f_module WHERE id='.(int)$moduleFixtureId)->fetch_row()[0]==='回归模型已修改',
        'model edit succeeds when disabled project input is absent');
    $disabledRoute='/admin/index.php?controller=modulemanage&operate=disabled&id='.$moduleFixtureId;
    expect_check(request('POST',$disabledRoute.'&f=1 OR 1=1',$cookies,['_csrf'=>$newToken])['status']===400,
        'model status cannot inject SQL after removing the legacy WAF');
    expect_check(request('POST',$disabledRoute.'&f=0',$cookies,['_csrf'=>$newToken])['status']===200 &&
        request('POST',$disabledRoute.'&f=1',$cookies,['_csrf'=>$newToken])['status']===200,
        'model status still toggles with validated values');
    $fieldRoute='/admin/index.php?controller=fieldmanage&project='.$moduleProject;
    $fieldPage=request('GET',$fieldRoute.'&operate=system',$cookies);
    expect_check($fieldPage['status']===200 && !str_contains($fieldPage['body'],'data.id') && str_contains($fieldPage['body'],'name="_csrf"'),'system field form has CSRF and does not build an undefined field ID');
    expect_check(preg_match('/name="record\[\]" value="15" checked disabled/',$fieldPage['body']) && preg_match('/name="record\[\]" value="16" checked disabled/',$fieldPage['body']),'existing status and URL fields cannot be selected again');
    expect_check(request('GET',$fieldRoute.'&operate=systemsave',$cookies)['status']===405,'system field addition requires POST');
    expect_check(request('POST',$fieldRoute.'&operate=systemsave',$cookies,['project'=>$moduleProject,'record'=>['2']])['status']===403,'system field addition requires CSRF');
    foreach (['2', [['2']], ['2 OR 1=1'], ['2','17'], ['2','0']] as $invalidRecords) {
        $invalidFields=request('POST',$fieldRoute.'&operate=systemsave',$cookies,['_csrf'=>$newToken,'project'=>$moduleProject,'record'=>$invalidRecords]);
        expect_check($invalidFields['status']===400,'system field selection rejects invalid values before DDL');
    }
    expect_check(!$db->query("SHOW COLUMNS FROM `f_$moduleProject` LIKE 'classid'")->num_rows,'invalid batches do not partially add columns');
    $systemFields=request('POST',$fieldRoute.'&operate=systemsave',$cookies,['_csrf'=>$newToken,'project'=>$moduleProject,'record'=>['2','3','12']]);
    expect_check(($systemFields['json']['status']??'')==='y','system field batch addition succeeds');
    $moduleColumns=array_column($db->query("SHOW COLUMNS FROM `f_$moduleProject`")->fetch_all(MYSQLI_ASSOC),'Field');
    expect_check(!array_diff(['classid','attrib_j','attrib_g','attrib_t','attrib_r','attrib_d','attrib_h','date'],$moduleColumns),'batch addition creates all selected physical columns');
    expect_check($db->query('SELECT id FROM f_module_field WHERE module_id='.(int)$moduleFixtureId.' AND name IN ("classid","attrib","date")')->num_rows===3,'system field metadata matches physical columns');
    $fieldRepeat=request('POST',$fieldRoute.'&operate=systemsave',$cookies,['_csrf'=>$newToken,'project'=>$moduleProject,'record'=>['2','3','12','12','15','16']]);
    expect_check(($fieldRepeat['json']['status']??'')==='y' && $db->query('SELECT id FROM f_module_field WHERE module_id='.(int)$moduleFixtureId)->num_rows===10,'repeated system field submission does not duplicate columns or metadata');
    $fieldEmpty=request('POST',$fieldRoute.'&operate=systemsave',$cookies,['_csrf'=>$newToken,'project'=>$moduleProject]);
    expect_check(($fieldEmpty['json']['status']??'')==='e','empty system field selection keeps the existing prompt');
    $fieldResult=request('GET',$fieldRoute.'&operate=execution&type=save&re=1',$cookies);
    expect_check($fieldResult['status']===200 && str_contains($fieldResult['body'],'操作成功') && !str_contains($fieldResult['body'],'继续修改'),'batch result page works without a single field ID');
    expect_check(request('GET',$fieldRoute.'&operate=execution&type=save&re=1&id=undefined',$cookies)['status']===400,'invalid numeric query parameters remain rejected');
    $sortingFields=$db->query('SELECT id FROM f_module_field WHERE module_id='.(int)$moduleFixtureId.' ORDER BY id LIMIT 2')->fetch_all(MYSQLI_ASSOC);
    $sortingIds=array_column($sortingFields,'id');
    $sortData=['_csrf'=>$newToken,'id'=>$sortingIds,'sort'=>['2','0']];
    expect_check((request('POST',$fieldRoute.'&operate=sort',$cookies,$sortData)['json']['status']??'')==='y','field sorting accepts the legitimate numeric ID array');
    expect_check((request('POST',$fieldRoute.'&operate=sort',$cookies,$sortData)['json']['status']??'')==='y','unchanged field sorting is successful');
    $missingSortId=(string)((int)$db->query('SELECT MAX(id)+100 FROM f_module_field')->fetch_row()[0]);
    $missingFieldSort=['_csrf'=>$newToken,'id'=>[$sortingIds[0],$missingSortId],'sort'=>['77','1']];
    expect_check((request('POST',$fieldRoute.'&operate=sort',$cookies,$missingFieldSort)['json']['status']??'')==='n' &&
        (int)$db->query('SELECT sort FROM f_module_field WHERE id='.(int)$sortingIds[0])->fetch_row()[0]===2,'missing field sort ID leaves prior rows unchanged');
    expect_check(request('POST',$fieldRoute.'&operate=sort',$cookies,['id'=>$sortingIds,'sort'=>['2','0']])['status']===403,'field sorting still requires CSRF');
    foreach ([['id'=>[['1']],'sort'=>['0']],['id'=>['1 OR 1=1'],'sort'=>['0']],['id'=>$sortingIds,'sort'=>['0']],['id'=>$sortingIds,'sort'=>['invalid','0']]] as $badSort) {
        expect_check(request('POST',$fieldRoute.'&operate=sort',$cookies,['_csrf'=>$newToken]+$badSort)['status']===400,'field sorting rejects malformed ID or ordering arrays');
    }
    expect_check(request('POST',$fieldRoute.'&operate=save',$cookies,$sortData)['status']===400,'ID arrays remain forbidden on single-record field saves');
    $customFieldData=['_csrf'=>$newToken,'module_id'=>$moduleFixtureId,'project'=>$moduleProject,'name'=>'qa_extra_'.$tag,'aliases'=>'回归字段','type'=>'17'];
    $customFieldSave=request('POST',$fieldRoute.'&operate=save',$cookies,$customFieldData);
    expect_check(($customFieldSave['json']['status']??'')==='y' && (int)($customFieldSave['json']['id']??0)>0,'custom field creation returns its real record ID');
    expect_check($db->query("SHOW COLUMNS FROM `f_$moduleProject` LIKE '".$customFieldData['name']."'")->num_rows===1,'custom field creation updates the declared module table');
    $export=request('GET','/admin/index.php?controller=modulemanage&operate=elicit&id='.$moduleFixtureId,$cookies);
    $package=json_decode($export['body'],true);
    expect_check($export['status']===200 && ($package['format']??'')==='fengcms-model-v1' &&
        ($package['source_project']??'')===$moduleProject && count($package['fields'])>=10,
        'model export returns structured portable definition');
    $modelRoute='/admin/index.php?controller=modulemanage';
    expect_check(request('GET',$modelRoute.'&operate=preview',$cookies)['status']===405,'model preview requires POST');
    $badPackage=$package;$badPackage['columns'][1]['name']='title`; DROP TABLE f_manage; --';
    $invalidPreview=request('POST',$modelRoute.'&operate=preview',$cookies,['_csrf'=>$newToken,'project'=>$importProject,'package_json'=>json_encode($badPackage)]);
    expect_check($invalidPreview['status']===400 && !$db->query("SHOW TABLES LIKE 'f_$importProject'")->num_rows,
        'model preview rejects injected column names before DDL');
    $preview=request('POST',$modelRoute.'&operate=preview',$cookies,['_csrf'=>$newToken,'project'=>$importProject,'package_json'=>$export['body']]);
    preg_match('/name="nonce" value="([a-f0-9]{32})"/',$preview['body'],$previewNonce);
    expect_check($preview['status']===200 && isset($previewNonce[1]) && str_contains($preview['body'],$customFieldData['name']),
        'model preview shows all custom fields without creating a table');
    expect_check(request('POST',$modelRoute.'&operate=loading',$cookies,['_csrf'=>$newToken,'modulecode'=>'legacy-sql'])['status']===400,
        'legacy SQL import code cannot execute');
    $import=request('POST',$modelRoute.'&operate=loading',$cookies,['_csrf'=>$newToken,'nonce'=>$previewNonce[1]]);
    $importFixtureId=$db->query("SELECT id FROM f_module WHERE project='$importProject'")->fetch_row()[0]??null;
    expect_check($import['status']===303 && $importFixtureId!==null,
        'confirmed model preview imports into a new project');
    $originalColumns=array_column($db->query("SHOW COLUMNS FROM `f_$moduleProject`")->fetch_all(MYSQLI_ASSOC),'Field');
    $importColumns=array_column($db->query("SHOW COLUMNS FROM `f_$importProject`")->fetch_all(MYSQLI_ASSOC),'Field');
    $importFields=$db->query('SELECT name,type FROM f_module_field WHERE module_id='.(int)$importFixtureId)->fetch_all(MYSQLI_ASSOC);
    expect_check($originalColumns===$importColumns && count($importFields)===count($package['fields']) &&
        in_array($customFieldData['name'],array_column($importFields,'name'),true),
        'imported model retains physical columns and field metadata');
    expect_check(request('POST',$modelRoute.'&operate=loading',$cookies,['_csrf'=>$newToken,'nonce'=>$previewNonce[1]])['status']===400,
        'model import confirmation cannot be replayed');
    $customFieldData['id']=$customFieldSave['json']['id'];
    expect_check((request('POST',$fieldRoute.'&operate=save',$cookies,$customFieldData)['json']['status']??'')==='y','unchanged custom field metadata can be saved again');
    $classifyRoute='/admin/index.php?controller=classify';
    foreach ([1,2] as $categoryNumber) {
        $db->query("INSERT INTO f_classify (name,type,project,classid,nav) VALUES ('qa_category_{$tag}_$categoryNumber','url','article',0,0)");
        $categoryIds[]=$db->insert_id;
    }
    $rootCategories=request('GET',$classifyRoute,$cookies);
    expect_check(str_contains($rootCategories['body'],'operate=sorts&classid=0'),'root category sorting link contains a valid zero parent ID');
    expect_check(request('GET',$classifyRoute.'&operate=sorts&classid=0',$cookies)['status']===200,'root category sorting page renders');
    $categorySort=['_csrf'=>$newToken,'id'=>$categoryIds,'sort'=>['3','0']];
    expect_check((request('POST',$classifyRoute.'&operate=order',$cookies,$categorySort)['json']['status']??'')==='y','category sorting accepts the legitimate numeric ID array');
    expect_check((request('POST',$classifyRoute.'&operate=order',$cookies,$categorySort)['json']['status']??'')==='y','unchanged category sorting is successful');
    expect_check((int)$db->query('SELECT sort FROM f_classify WHERE id='.(int)$categoryIds[0])->fetch_assoc()['sort']===3,'category sort values persist');
    $missingCategoryId=(string)((int)$db->query('SELECT MAX(id)+100 FROM f_classify')->fetch_row()[0]);
    $missingCategorySort=['_csrf'=>$newToken,'id'=>[$categoryIds[0],$missingCategoryId],'sort'=>['77','1']];
    expect_check((request('POST',$classifyRoute.'&operate=order',$cookies,$missingCategorySort)['json']['status']??'')==='n' &&
        (int)$db->query('SELECT sort FROM f_classify WHERE id='.(int)$categoryIds[0])->fetch_row()[0]===3,'missing category sort ID leaves prior rows unchanged');
    expect_check(request('POST',$classifyRoute.'&operate=save',$cookies,$categorySort)['status']===400,'ID arrays remain forbidden on single-category saves');
    $db->query("INSERT INTO f_classify (name,type,project,classid,nav) VALUES ('qa_nested_$tag','url','article',".(int)$categoryIds[0].",0)");
    $categoryIds[]=$db->insert_id;
    $mapPage=request('GET','/map.html',$cookies);
    expect_check($mapPage['status']===200 && preg_match('~qa_category_'.preg_quote($tag,'~').'_1</a>\s*<ul>\s*<li><a[^>]*>qa_nested_'.preg_quote($tag,'~').'~',$mapPage['body'])===1,
        'site map renders nested categories from one tree');
    foreach ([['module',$moduleProject],['classify',null],['fieldmanage',$moduleProject],['modulemanage',null],['message',null],['signup',null],['recover',$moduleProject],['tplmanage','tpl']] as [$resultController,$resultProject]) {
        foreach (['0','1'] as $resultState) {
            $resultRoute='/admin/index.php?controller='.$resultController.'&operate=execution&type=save&re='.$resultState.($resultProject?'&project='.$resultProject:'');
            $resultPage=request('GET',$resultRoute,$cookies);
            expect_check($resultPage['status']===200,'result page accepts absent optional record ID: '.$resultController.' re='.$resultState);
            preg_match_all('/href="([^"]+)"/',$resultPage['body'],$resultLinks);$validLinks=true;
            foreach ($resultLinks[1] as $resultLink) {
                parse_str(parse_url(html_entity_decode($resultLink,ENT_QUOTES|ENT_HTML5,'UTF-8'),PHP_URL_QUERY)??'',$resultQuery);
                foreach (['id','classid','page','module_id'] as $numericKey) {
                    if(isset($resultQuery[$numericKey]) && (!is_string($resultQuery[$numericKey]) || !preg_match('/^[0-9]{1,10}$/D',$resultQuery[$numericKey]))){$validLinks=false;}
                }
            }
            expect_check($validLinks,'result page emits no empty numeric links: '.$resultController.' re='.$resultState);
        }
    }
    $db->query("INSERT INTO `f_$moduleProject` (title,status) VALUES ('result_$tag',1)");$resultContentId=$db->insert_id;
    foreach ([['module',$moduleProject,$resultContentId],['classify',null,$categoryIds[0]],['fieldmanage',$moduleProject,$customFieldSave['json']['id']],['modulemanage',null,$moduleFixtureId]] as [$resultController,$resultProject,$resultId]) {
        $resultPage=request('GET','/admin/index.php?controller='.$resultController.'&operate=execution&type=save&re=1&id='.$resultId.($resultProject?'&project='.$resultProject:''),$cookies);
        expect_check($resultPage['status']===200 && str_contains($resultPage['body'],'operate=update&id='.$resultId),'valid record IDs still provide edit links: '.$resultController);
    }
    expect_check(request('GET','/admin/index.php?controller=module&project=manage',$cookies)['status']===404,'content editor cannot address administrator table');
    $articleTitle='probe_'.$tag.' <b data-probe="title">';
    $rich='<p>安全回归正文 '.$tag.'</p>';
    $articleStmt=$db->prepare('INSERT INTO f_article (title,content,info,template,classid,status,time,date) VALUES (?,?,?,"/article_content.html","0",?,UNIX_TIMESTAMP(),CURDATE())');
    $status=1; $articleStmt->bind_param('sssi',$articleTitle,$rich,$tag,$status); $articleStmt->execute(); $articleId=$db->insert_id; $articleIds[]=$articleId;
    $status=0; $articleStmt->execute(); $draftId=$db->insert_id; $articleIds[]=$draftId;
    $shiftRoute='/admin/index.php?controller=module&project=article&operate=shift';
    expect_check(request('POST',$shiftRoute,$cookies,['_csrf'=>$newToken,'classid'=>(string)$categoryIds[0],'classarray'=>$articleId.' OR 1=1'])['status']===400,
        'article shift rejects injected ID lists');
    expect_check(request('POST',$shiftRoute,$cookies,['_csrf'=>$newToken,'classid'=>(string)$categoryIds[0],'classarray'=>(string)$articleId])['status']===200 &&
        (int)$db->query('SELECT classid FROM f_article WHERE id='.(int)$articleId)->fetch_row()[0]===(int)$categoryIds[0],
        'article shift accepts validated IDs');
    $public=request('GET','/?controller=content&project=article&id='.$articleId,$cookies);
    expect_check($public['status']===200 && str_contains($public['body'],'&lt;b data-probe=&quot;title&quot;&gt;') && !str_contains($public['body'],'<b data-probe="title">'),'article title is escaped');
    expect_check(str_contains($public['body'],$rich),'legacy content variable retains rich markup');
    expect_check(str_contains($public['body'],'&gt;&gt;') && !str_contains($public['body'],'&amp;gt;'),
        'breadcrumb separator is HTML-escaped exactly once');

    expect_check(request('GET','/?controller=content&project=article&id='.$draftId,$cookies)['status']===404,'unpublished article is private');
    $query='/index.php?controller=search&tags='.rawurlencode('<b data-probe="search">');
    $search=request('GET',$query,$cookies);
    expect_check($search['status']===200 && str_contains($search['body'],'&lt;b data-probe=&quot;search&quot;&gt;') && !str_contains($search['body'],'<b data-probe="search">'),'search reflects escaped text');
    $scriptSearch=request('GET','/?controller=search&tags='.rawurlencode('<script>alert(1)</script>'),$cookies);
    expect_check($scriptSearch['status']===200 && str_contains($scriptSearch['body'],'&lt;script&gt;alert(1)&lt;/script&gt;') &&
        !str_contains($scriptSearch['body'],'<script>alert(1)</script>'), 'normal search text is escaped without keyword blocking');
    $query='/index.php?controller=search&project=article&tags='.rawurlencode('" OR 1=1 #');
    $search=request('GET',$query,$cookies);
    expect_check($search['status']===200 && !str_contains($search['body'],$tag),'search SQL injection cannot return unrelated articles');
    $allSearch='/?controller=search&project=&tags='.rawurlencode('搜索');
    $search=request('GET',$allSearch,$cookies);
    expect_check($search['status']===200 && str_contains($search['body'],'站内搜索') && !str_contains($search['body'],'模型名称不正确'),'all-model search accepts the existing empty project URL');
    expect_check(request('GET','/?controller=search&tags='.rawurlencode('搜索'),$cookies)['status']===200,'all-model search also accepts an omitted project');
    $searchPost=request('POST','/?controller=search',$cookies,['project'=>'','tags'=>'搜索']);
    expect_check($searchPost['status']===303 && preg_match('~^Location: /\?controller=search&tags=%E6%90%9C%E7%B4%A2\s*$~m',$searchPost['headers'])===1,'all-model search form redirects without an empty project parameter');
    expect_check(request('GET','/?controller=search&project=article&tags='.rawurlencode('搜索'),$cookies)['status']===200,'named-model search still works');
    $pageInsert=$db->prepare("INSERT INTO f_article (title,content,info,template,classid,status,time,date) VALUES (?,'','','/article_content.html',0,1,UNIX_TIMESTAMP(),CURDATE())");
    $pageTitle=''; $pageInsert->bind_param('s',$pageTitle);
    for ($i=0; $i<12; $i++) { $pageTitle='page_'.$tag.'_'.$i; $pageInsert->execute(); $articleIds[]=$db->insert_id; }
    $pageQuery='/?controller=search&project=article&tags='.rawurlencode('page_'.$tag);
    $firstPage=request('GET',$pageQuery,$cookies);
    $secondPage=request('GET',$pageQuery.'&page=2',$cookies);
    preg_match_all('~<strong><a [^>]*>page_'.preg_quote($tag,'~').'_\d+</a></strong>~',$firstPage['body'],$firstMatches);
    preg_match_all('~<strong><a [^>]*>page_'.preg_quote($tag,'~').'_\d+</a></strong>~',$secondPage['body'],$secondMatches);
    expect_check($firstPage['status']===200 && $secondPage['status']===200 &&
        count($firstMatches[0])===10 && count($secondMatches[0])===2 &&
        str_contains($firstPage['body'],'12条') && str_contains($secondPage['body'],'12条'),
        'search uses bounded database pagination with a correct total');
    expect_check(request('GET',$pageQuery.'&page=101',$cookies)['status']===400 &&
        request('GET','/?controller=search&tags='.str_repeat('a',81),$cookies)['status']===400,
        'search bounds page and keyword length');
    expect_check(request('GET','/?controller=search&project='.rawurlencode('../admin').'&tags=test',$cookies)['status']===400,'malformed search model remains rejected');
    expect_check(request('GET','/?controller=content&project=&id='.$articleId,$cookies)['status']===400,'empty project remains forbidden outside search');
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
    $recycleIds=[];
    foreach ([-1,-1,-1] as $recycleStatus) {
        $db->query("INSERT INTO `f_$moduleProject` (title,status) VALUES ('recycle_$tag',$recycleStatus)");
        $recycleIds[]=$db->insert_id;
    }
    $recoverRoute='/admin/index.php?controller=recover&project='.$moduleProject;
    $recoverBatch=request('POST',$recoverRoute.'&operate=batch',$cookies,['_csrf'=>$newToken,'method'=>'recovers','article'=>array_slice($recycleIds,0,2)]);
    expect_check(($recoverBatch['json']['status']??'')==='y' && $db->query("SELECT id FROM `f_$moduleProject` WHERE id IN (".implode(',',array_slice($recycleIds,0,2)).') AND status=1')->num_rows===2,'recycle bin batch restoration really restores selected records');
    expect_check(($recoverBatch['json']['project']??'')===$moduleProject,'recycle bin batch response retains the selected module for result navigation');
    $recoverResult=request('GET',$recoverRoute.'&operate=execution&type=save&re=1',$cookies);
    expect_check($recoverResult['status']===200 && str_contains($recoverResult['body'],'操作成功') && str_contains($recoverResult['body'],'project='.$moduleProject) && str_contains($recoverResult['body'],'返回回收站'),'recycle bin batch result works without a record ID and retains the module');
    $recoverFailure=request('GET',$recoverRoute.'&operate=execution&type=save&re=0',$cookies);
    expect_check($recoverFailure['status']===200 && str_contains($recoverFailure['body'],'操作失败') && str_contains($recoverFailure['body'],'project='.$moduleProject),'recycle bin failure result also returns to the same module');
    expect_check(request('GET',$recoverRoute.'&operate=recoverone&id='.$recycleIds[2],$cookies)['status']===405,'single record restoration requires POST');
    expect_check(request('POST',$recoverRoute.'&operate=batch',$cookies,['method'=>'recovers','article'=>[$recycleIds[2]]])['status']===403,'recycle bin batch writes require CSRF');
    foreach (['x', [['1']], [$recycleIds[2],'1 OR 1=1'], [$recycleIds[2],'0']] as $invalidIds) {
        expect_check(request('POST',$recoverRoute.'&operate=batch',$cookies,['_csrf'=>$newToken,'method'=>'recovers','article'=>$invalidIds])['status']===400,'recycle bin rejects malformed selections before changing records');
    }
    expect_check((int)$db->query("SELECT status FROM `f_$moduleProject` WHERE id=".$recycleIds[2])->fetch_assoc()['status']===-1,'invalid selections do not partially restore valid records');
    $recoverEmpty=request('POST',$recoverRoute.'&operate=batch',$cookies,['_csrf'=>$newToken,'method'=>'recovers']);
    expect_check(($recoverEmpty['json']['status']??'')==='a' && ($recoverEmpty['json']['project']??'')===$moduleProject,'empty recycle bin selection preserves its prompt and module');
    $recoverSingle=request('POST',$recoverRoute.'&operate=recoverone&id='.$recycleIds[2],$cookies,['_csrf'=>$newToken]);
    expect_check($recoverSingle['status']===200 && str_contains($recoverSingle['body'],'project='.$moduleProject) && str_contains($recoverSingle['body'],'re=1') && (int)$db->query("SELECT status FROM `f_$moduleProject` WHERE id=".$recycleIds[2])->fetch_assoc()['status']===1,'single record restoration retains its module and succeeds');
    $recoverRepeat=request('POST',$recoverRoute.'&operate=batch',$cookies,['_csrf'=>$newToken,'method'=>'recovers','article'=>[$recycleIds[2]]]);
    expect_check(($recoverRepeat['json']['status']??'')==='n' && ($recoverRepeat['json']['project']??'')===$moduleProject,'stale restoration reports failure with valid navigation context');
    $safeDelete=request('POST',$recoverRoute.'&operate=delete&id='.$recycleIds[2],$cookies,['_csrf'=>$newToken]);
    expect_check(str_contains($safeDelete['body'],'re=0') && $db->query("SELECT id FROM `f_$moduleProject` WHERE id=".$recycleIds[2])->num_rows===1,'recycle bin deletion cannot remove an already restored record');
    expect_check(request('POST',$recoverRoute.'&operate=batch',$cookies,['_csrf'=>$newToken,'method'=>'index','article'=>[$recycleIds[0]]])['status']===400,'recycle bin cannot dispatch arbitrary batch methods');
    expect_check(request('GET',$recoverRoute.'&operate=recovers',$cookies)['status']===404,'recycle bin internal batch helpers cannot be called as routes');
    expect_check(request('GET',$recoverRoute.'&operate=execution&type=save&re=1&id=undefined',$cookies)['status']===400,'invalid numeric query parameters are still rejected after recycle bin repair');

    $base=request('POST','/admin/index.php?controller=base&operate=save',$cookies,['_csrf'=>$newToken,'name'=>'base_'.$tag,'url_type'=>'1']);
    expect_check(($base['json']['status']??'')==='y','site settings save without writing PHP source');
    expect_check(request('GET','/tags.html',$cookies)['status']===200,'tag cloud URL works in pseudo-static mode');
    $copyrightSave=request('POST','/admin/index.php?controller=base&operate=save',$cookies,['_csrf'=>$newToken,'copyright'=>$attack,'url_type'=>'1']);
    $copyright=$db->query('SELECT copyright FROM f_base LIMIT 1')->fetch_assoc()['copyright'];
    expect_check(($copyrightSave['json']['status']??'')==='y' && !str_contains($copyright,'<script') && str_contains($copyright,'<strong>加粗</strong>'), 'copyright HTML uses the server sanitizer');

    expect_check(request('GET','/index.html',$cookies)['status']===200,'rewrite mode renders homepage');
    request('POST','/admin/index.php?controller=base&operate=save',$cookies,['_csrf'=>$newToken,'name'=>$originalName,'url_type'=>'0']);
    expect_check(request('GET','/tags.html',$cookies)['status']===200 && request('GET','/?controller=tags',$cookies)['status']===200,'tag cloud URLs work in dynamic mode');
    foreach (['home&operate=main','base','manage&operate=password','modulemanage','module&project=article','classify','message','tplmanage','dbmanage&operate=dbmanages'] as $route) {
        $page=request('GET','/admin/index.php?controller='.$route,$cookies);
        expect_check($page['status']===200 && str_contains($page['body'],'<html'),'admin screen renders: '.$route);
    }
    $templateRoute='/admin/index.php?controller=tplmanage';
    $templateName='qa_template_'.$tag;
    $templateSource='<h1>模板回归 '.$tag.'</h1><p>{$title}</p><script>window.templateQA="'.$tag.'";</script>';
    expect_check(request('GET',$templateRoute.'&operate=save',$cookies)['status']===405,'template save requires POST');
    expect_check(request('POST',$templateRoute.'&operate=save',$cookies,['project'=>'tpl','name'=>$templateName,'type'=>'0','content'=>$templateSource])['status']===403,'template save requires CSRF');
    $anonymous=[];
    expect_check(request('POST',$templateRoute.'&operate=save',$anonymous,['_csrf'=>$newToken,'project'=>'tpl','name'=>$templateName,'type'=>'0','content'=>$templateSource])['status']===401,'template source editing requires administrator authentication');
    foreach (['tpl'=>'template','css'=>'css','scrap'=>'template/scrap','inc'=>'template/inc','show'=>'template/show'] as $templateType=>$templateDirectory) {
        $extension=$templateType==='css'?'.css':'.html';
        $templateFile=dirname(__DIR__).'/'.$templateDirectory.'/'.$templateName.$extension;
        $files[]=$templateFile;
        $source=$templateType==='css'?'body { color:#123456; }':$templateSource;
        $templateData=['_csrf'=>$newToken,'project'=>$templateType,'name'=>$templateName,'type'=>'0','content'=>$source];
        $templateSave=request('POST',$templateRoute.'&operate=save',$cookies,$templateData);
        expect_check(($templateSave['json']['status']??'')==='y' && is_file($templateFile) && file_get_contents($templateFile)===$source,'template manager creates exact source: '.$templateType);
        $templateEdit=request('GET',$templateRoute.'&operate=update&project='.$templateType.'&name='.$templateName,$cookies);
        preg_match('~<textarea\b[^>]*name="content"[^>]*>(.*?)</textarea>~s',$templateEdit['body'],$templateTextarea);
        expect_check($templateEdit['status']===200 && isset($templateTextarea[1]) && html_entity_decode($templateTextarea[1],ENT_QUOTES|ENT_HTML5,'UTF-8')===$source,'template source reopens without executing or double escaping: '.$templateType);
        $templateDuplicate=request('POST',$templateRoute.'&operate=save',$cookies,$templateData);
        expect_check(($templateDuplicate['json']['status']??'')==='e' && file_get_contents($templateFile)===$source,'new template cannot overwrite an existing name: '.$templateType);
        $templateData['type']='1';$templateData['content']='';
        $emptyTemplate=request('POST',$templateRoute.'&operate=save',$cookies,$templateData);
        clearstatcache(true,$templateFile);
        expect_check(($emptyTemplate['json']['status']??'')==='y' && filesize($templateFile)===0,'zero-byte template save is successful: '.$templateType);
        $templateData['content']=$source;
        expect_check((request('POST',$templateRoute.'&operate=save',$cookies,$templateData)['json']['status']??'')==='y' && file_get_contents($templateFile)===$source,'template manager updates exact source: '.$templateType);
        $templateList=request('GET',$templateRoute.'&project='.$templateType,$cookies);
        preg_match_all('~class="art_title_txt"><a[^>]*>([^<]+)</a>~',$templateList['body'],$listedTemplates);
        $orderedTemplates=array_map(static fn($name)=>html_entity_decode($name,ENT_QUOTES|ENT_HTML5,'UTF-8'),$listedTemplates[1]);
        $expectedTemplates=$orderedTemplates;
        usort($expectedTemplates,static fn($a,$b)=>strnatcasecmp($a,$b) ?: strcmp($a,$b));
        expect_check($orderedTemplates===$expectedTemplates && in_array($templateName.$extension,$orderedTemplates,true),'template list sorts file names A-Z: '.$templateType);
        expect_check(!glob(dirname($templateFile).'/.fengcms-*'),'template writes clean up temporary files: '.$templateType);
    }
    $db->query('UPDATE f_article SET template="/'.$templateName.'.html" WHERE id='.(int)$articleId);
    $renderedTemplate=request('GET','/?controller=content&project=article&id='.$articleId,$cookies);
    expect_check($renderedTemplate['status']===200 && str_contains($renderedTemplate['body'],'模板回归 '.$tag) && !str_contains($renderedTemplate['body'],'{$title}'),'new template can render a frontend article');
    $db->query('UPDATE f_article SET template="/article_content.html" WHERE id='.(int)$articleId);
    expect_check(request('GET','/template/'.$templateName.'.html',$cookies)['status']===404,'new template source is not publicly downloadable');
    expect_check(request('GET','/css/'.$templateName.'.css',$cookies)['body']==='body { color:#123456; }','saved stylesheet is served by Nginx');
    foreach ([['name'=>'../escape'],['name'=>'nested/file'],['name'=>'x.php/evil'],['name'=>['array']],['project'=>'../template'],['type'=>'2'],['type'=>['0']],['content'=>['array']]] as $invalidTemplate) {
        $invalidTemplateSave=request('POST',$templateRoute.'&operate=save',$cookies,array_replace(['_csrf'=>$newToken,'project'=>'tpl','name'=>$templateName,'type'=>'0','content'=>'valid'],$invalidTemplate));
        expect_check($invalidTemplateSave['status']===400,'invalid template save parameters are rejected');
    }
    $missingTemplate=request('POST',$templateRoute.'&operate=save',$cookies,['_csrf'=>$newToken,'project'=>'tpl','name'=>'qa_missing_'.$tag,'type'=>'1','content'=>'missing']);
    expect_check($missingTemplate['status']===404,'template update cannot create a missing file');
    $symlinkName='qa_link_'.$tag;$symlinkPath=dirname(__DIR__).'/template/'.$symlinkName.'.html';
    symlink(dirname(__DIR__).'/config.php',$symlinkPath);$files[]=$symlinkPath;
    $symlinkSave=request('POST',$templateRoute.'&operate=save',$cookies,['_csrf'=>$newToken,'project'=>'tpl','name'=>$symlinkName,'type'=>'1','content'=>'no']);
    expect_check($symlinkSave['status']===400,'template save refuses symbolic links');
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
    $replyData=['_csrf'=>$newToken,'id'=>$messageId,'reply'=>'谢谢反馈，已处理。','status'=>'1','rtime'=>(string)time()];
    $replySave=request('POST','/admin/index.php?controller=message&operate=save',$cookies,$replyData);
    expect_check(($replySave['json']['status']??'')==='y' && (int)($replySave['json']['id']??0)===(int)$messageId,'message reply returns the actual saved record ID');
    expect_check($db->query('SELECT reply FROM f_message WHERE id='.(int)$messageId)->fetch_assoc()['reply']===$replyData['reply'],'message reply is persisted');
    $replyResult=request('GET','/admin/index.php?controller=message&operate=execution&re=1&id='.$replySave['json']['id'],$cookies);
    expect_check($replyResult['status']===200 && str_contains($replyResult['body'],'操作成功'),'message reply result URL accepts its returned record ID');
    expect_check((request('POST','/admin/index.php?controller=message&operate=save',$cookies,$replyData)['json']['status']??'')==='y','saving an unchanged message reply is successful');
    $db->query("CREATE TABLE `$fixture` (id int AUTO_INCREMENT PRIMARY KEY,name varchar(255),note text,payload blob) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $text="单引号' 双引号\" 反斜杠\\ 1e10 ; DROP TABLE test";
    $binary="\x00\xff\x0a<?php";
    $id=D(substr($fixture,2))->insert(['name'=>$text,'note'=>null,'payload'=>$binary]);
    $row=D(substr($fixture,2))->where(['name'=>$text])->getone();
    expect_check($row && $row['name']===$text && $row['note']===null && $row['payload']===$binary,'prepared insert/where preserve quotes, Unicode, binary and NULL');
    D(substr($fixture,2))->where(['id'=>$id])->update(['note'=>$text]);
    expect_check(D(substr($fixture,2))->where(['id'=>$id])->getone()['note']===$text,'prepared update binds values and conditions');
    $backupRoute='/admin/index.php?controller=dbmanage';
    $backupDir=dirname(__DIR__).'/admin/app/dbbackup';
    $before=glob($backupDir.'/*.json') ?: [];
    expect_check(request('GET',$backupRoute.'&operate=dbregain',$cookies)['status']===404,'unsafe web restore is unavailable');
    expect_check(request('GET',$backupRoute.'&operate=create',$cookies)['status']===405,'backup creation requires POST');
    expect_check(request('POST',$backupRoute.'&operate=create',$cookies)['status']===403,'backup creation requires CSRF');
    $backupCreated=request('POST',$backupRoute.'&operate=create',$cookies,['_csrf'=>$newToken]);
    $after=glob($backupDir.'/*.json') ?: [];
    $newBackups=array_values(array_diff($after,$before));
    $backupId=count($newBackups)===1 ? basename($newBackups[0],'.json') : null;
    expect_check($backupCreated['status']===303 && $backupId!==null,'administrator creates one complete database backup');
    $meta=json_decode(file_get_contents($newBackups[0]),true);
    expect_check(($meta['format']??0)===1 && count($meta['objects']??[])>=16 &&
        in_array($fixture,array_column($meta['objects'],'name'),true),'backup manifest includes tables and test fixture');
    $anonymous=[];
    expect_check(request('POST',$backupRoute.'&operate=download',$anonymous,['_csrf'=>$newToken,'backup_id'=>$backupId])['status']===401,
        'anonymous backup download rejected');
    expect_check(request('POST',$backupRoute.'&operate=download',$cookies,['backup_id'=>$backupId])['status']===403,
        'backup download requires CSRF');
    expect_check(request('POST',$backupRoute.'&operate=download',$cookies,['_csrf'=>$newToken,'backup_id'=>'../config'])['status']===400,
        'backup download rejects traversal');
    $download=request('POST',$backupRoute.'&operate=download',$cookies,['_csrf'=>$newToken,'backup_id'=>$backupId]);
    expect_check($download['status']===200 && hash('sha256',$download['body'])===$meta['sha256'] &&
        strlen($download['body'])===$meta['bytes'] && str_contains($download['body'],' VIEW `') &&
        str_contains($download['body'],'0x'.bin2hex($binary)) &&
        str_contains($download['body'],$fixture),'backup download matches manifest and includes view');
    expect_check(request('GET','/admin/app/dbbackup/'.$backupId.'.sql',$cookies)['status']===404,
        'backup file is not publicly reachable');
    expect_check(request('POST',$backupRoute.'&operate=delete',$cookies,['_csrf'=>$newToken,'backup_id'=>$backupId])['status']===303 &&
        !is_file($newBackups[0]),'administrator deletes selected new backup');
    $backupId=null;
    $rejected=false; try { D('manage;DROP'); } catch (InvalidArgumentException $e) { $rejected=true; }
    expect_check($rejected,'SQL table identifier injection rejected');
    $legacyCookies=[]; $legacyToken=token($legacyCookies);
    $legacyLogin=['_csrf'=>$legacyToken,'user'=>$legacyUser,'password'=>$legacyPassword,'admincode'=>getenv('ADMIN_CODE')];
    expect_check((request('POST','/admin/index.php?controller=admin',$legacyCookies,$legacyLogin)['json']['status']??'')==='y','legacy triple-MD5 login succeeds');
    $row=$db->query('SELECT password FROM f_manage WHERE id='.(int)$legacyId)->fetch_assoc();
    expect_check(strlen($row['password'])>32 && password_verify($legacyPassword,$row['password']),'legacy hash upgrades');
    $originalCliSession=$_SESSION;
    $_SESSION=['manage_id'=>(int)$testId,'manage'=>$user,'auth_at'=>time()-43201,
        'last_seen'=>time(),'auth_fingerprint'=>'invalid','csrf_token'=>bin2hex(random_bytes(32))];
    expect_check(!cms_admin_valid() && empty($_SESSION['manage_id']),'absolute admin session expiry is enforced');
    $_SESSION=['manage_id'=>(int)$testId,'manage'=>$user,'auth_at'=>time(),
        'last_seen'=>time()-3601,'auth_fingerprint'=>'invalid','csrf_token'=>bin2hex(random_bytes(32))];
    expect_check(!cms_admin_valid() && empty($_SESSION['manage_id']),'idle admin session expiry is enforced');
    $_SESSION=$originalCliSession;
    $oldPasswordHash=$db->query('SELECT password FROM f_manage WHERE id='.(int)$testId)->fetch_row()[0];
    $newPassword=bin2hex(random_bytes(16));
    $change=request('POST','/admin/index.php?controller=manage&operate=save',$cookies,['_csrf'=>$newToken,'user'=>$user,'password'=>$password,'newpassword'=>$newPassword,'newpasswords'=>$newPassword]);
    expect_check(($change['json']['status']??'')==='y','administrator changes own password');
    $own=$db->query('SELECT password FROM f_manage WHERE id='.(int)$testId)->fetch_assoc();
    $other=$db->query('SELECT password FROM f_manage WHERE id='.(int)$legacyId)->fetch_assoc();
    expect_check(password_verify($newPassword,$own['password']) && password_verify($legacyPassword,$other['password']),'password change leaves other accounts intact');
    expect_check(request('GET','/admin/index.php',$cookies)['status']===302,'password change logs out');
    $_SESSION=['manage_id'=>(int)$testId,'manage'=>$user,'auth_at'=>time(),
        'last_seen'=>time(),'auth_fingerprint'=>hash_hmac('sha256',$oldPasswordHash,getenv('APP_KEY')),
        'csrf_token'=>bin2hex(random_bytes(32))];
    expect_check(!cms_admin_valid() && empty($_SESSION['manage_id']),'password change revokes other sessions');
    $_SESSION=$originalCliSession;
    $rateCookies=[]; $rateToken=token($rateCookies);
    for ($i=0;$i<11;$i++) {
        $rate=request('POST','/admin/index.php?controller=admin',$rateCookies,['_csrf'=>$rateToken,'user'=>'absent_'.$tag,'password'=>'incorrect','admincode'=>getenv('ADMIN_CODE')]);
    }
    expect_check($rate['status']===429,'login rate limit enforced');
    echo "All $passed regression checks passed.\n";
} finally {
    if ($backupId !== null) {
        foreach (['sql','json'] as $extension) {
            $file=dirname(__DIR__).'/admin/app/dbbackup/'.$backupId.'.'.$extension;
            if (is_file($file)) { unlink($file); }
        }
    }
    foreach ($categoryIds as $categoryId) { $db->query('DELETE FROM f_classify WHERE id='.(int)$categoryId); }
    if ($moduleFixtureId) {
        $db->query('DELETE FROM f_module_field WHERE module_id='.(int)$moduleFixtureId);
        $db->query('DELETE FROM f_module WHERE id='.(int)$moduleFixtureId);
    }
    if ($importFixtureId) {
        $db->query('DELETE FROM f_module_field WHERE module_id='.(int)$importFixtureId);
        $db->query('DELETE FROM f_module WHERE id='.(int)$importFixtureId);
    }
    $db->query("DROP TABLE IF EXISTS `f_$importProject`");
    $db->query("DROP TABLE IF EXISTS `f_$moduleProject`");
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
