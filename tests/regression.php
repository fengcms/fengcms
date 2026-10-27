<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Keep CLI progress output from breaking the in-process session/header checks.
ob_start();
require dirname(__DIR__).'/tools/migrate.php';
require_once dirname(__DIR__).'/system/common/filter.php';
require_once dirname(__DIR__).'/system/common/richtext.php';
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
function visitor_token(array &$cookies): string {
    $page=request('GET','/?controller=message&operate=add',$cookies);
    if ($page['status']!==200 || !str_contains($page['body'],'/js/csrf.js') ||
        !preg_match('/name="csrf-token" content="([a-f0-9]+)"/',$page['body'],$m)) {
        throw new RuntimeException('Public message page has no CSRF token.');
    }
    return $m[1];
}
function upload(array &$cookies,string $token,string $name,string $content, string $dir="image"): array {
    $b = 'fengcms'.bin2hex(random_bytes(8));
    $body = "--$b\r\nContent-Disposition: form-data; name=\"_csrf\"\r\n\r\n$token\r\n";
    $body .= "--$b\r\nContent-Disposition: form-data; name=\"imgFile\"; filename=\"$name\"\r\nContent-Type: application/octet-stream\r\n\r\n$content\r\n--$b--\r\n";
    return request('POST','/admin/api/upload.php?dir='.rawurlencode($dir),$cookies,$body,['Content-Type'=>'multipart/form-data; boundary='.$b]);
}
$tag=bin2hex(random_bytes(6)); $user='test_'.$tag; $legacyUser='legacy_'.$tag;
$extraBaseCopyright='audit_extra_'.$tag;
$password=bin2hex(random_bytes(16)); $legacyPassword=bin2hex(random_bytes(16))."'/&";
$files=[]; $directories=[]; $fixture='f_security_test_'.$tag; $viewFixture='f_security_view_'.$tag; $testId=$legacyId=$editorId=$extraSuperId=null; $backupId=null;
$editorUser='editor_'.$tag; $editorPassword='EdPass8!'; $extraSuperUser='super_'.$tag;
$editorTagId=$editorCategoryId=$editorArticleId=$editorMessageId=$batchMessageId=null;
$unsafeTagId=$unsafeCategoryId=$unsafeFriendId=$legacyUnsafeTagId=$legacyUnsafeFriendId=$legacyUnsafeCategoryId=$legacyUnsafeAuthorId=$badExternalId=null;
$counterTagIds=[]; $counterAuthorId=null;
$signupFixtureCreated=false;
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
    $tagOverride=request('GET','/tags.html?controller=home',$cookies);
    expect_check($tagOverride['status']===200 && str_contains($tagOverride['body'],'id="tags_list"'),
        'rewrite route cannot be overridden by a query parameter');
    $tagPathOverride=request('GET','/tags/x&controller=home.html',$cookies);
    expect_check($tagPathOverride['status']!==200 || str_contains($tagPathOverride['body'],'站内搜索'),
        'rewrite route cannot be overridden from a path capture');
    expect_check(str_contains(request('GET','/?controller=tags',$cookies)['body'],'id="tags_list"'),'dynamic tag cloud URL remains available');
    $captchaCookies=[];
    expect_check(request('GET','/vccore.php',$captchaCookies)['status']===200,'visitor can request a captcha image');
    $visitorToken=visitor_token($captchaCookies);
    expect_check(request('POST','/?controller=home&operate=vccore',$captchaCookies,['param'=>'abcd'])['status']===403,
        'asynchronous captcha validation requires the visitor CSRF token');
    $arrayCaptcha=request('POST','/?controller=home&operate=vccore',$captchaCookies,
        ['param'=>['abcd']],['X-CSRF-Token'=>$visitorToken]);
    expect_check($arrayCaptcha['status']!==500 && $arrayCaptcha['body']!=='y',
        'captcha verification rejects array input without a PHP error');
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
    expect_check(str_contains($page['body'],'public/jquery/jquery-4.0.0.min.js') &&
        str_contains($page['body'],'/js/jquery-compat.js?v=4.0.0') &&
        !str_contains($page['body'],'public/jquery/jquery.js') &&
        request('GET','/admin/public/jquery/jquery-4.0.0.min.js',$cookies)['status']===200,
        'login loads the bundled jQuery before Validform');
    $hash=password_hash($password,PASSWORD_DEFAULT);
    $stmt=$db->prepare("INSERT INTO f_manage (admin,password,role,datetime) VALUES (?,?,'super_admin',NOW())");
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
    $accountPage=request('GET','/admin/index.php?controller=account',$cookies);
    expect_check($accountPage['status']===200 && str_contains($accountPage['body'],'系统账号管理') &&
        str_contains($accountPage['body'],'operate=add') && !str_contains($accountPage['body'],'id="account-create"'),
        'account list links to a separate creation page');
    $accountAdd=request('GET','/admin/index.php?controller=account&operate=add',$cookies);
    expect_check($accountAdd['status']===200 && str_contains($accountAdd['body'],'id="account-create"') &&
        str_contains($accountAdd['body'],'name="role"') && !str_contains($accountAdd['body'],'id="art_list"'),
        'account creation has its own form page');
    $systemMenu=request('GET','/admin/index.php',$cookies);
    expect_check(str_contains($systemMenu['body'],'系统账号管理') &&
        !str_contains($systemMenu['body'],'>编辑账号</a>'),
        'system account management appears in system menu, not top bar');
    $shortPassword=request('POST','/admin/index.php?controller=account&operate=create',$cookies,
        ['_csrf'=>$newToken,'username'=>'short_'.$tag,'password'=>'1234567','role'=>'editor']);
    expect_check($shortPassword['status']===400,'account creation rejects passwords shorter than eight characters');
    $badRole=request('POST','/admin/index.php?controller=account&operate=create',$cookies,
        ['_csrf'=>$newToken,'username'=>'role_'.$tag,'password'=>$editorPassword,'role'=>'owner']);
    expect_check($badRole['status']===400,'account creation accepts only fixed roles');
    $createdEditor=request('POST','/admin/index.php?controller=account&operate=create',$cookies,
        ['_csrf'=>$newToken,'username'=>$editorUser,'password'=>$editorPassword,'role'=>'editor']);
    preg_match('/本次生成的认证码：<strong[^>]*>([A-HJ-NP-Z2-9]{4})<\/strong>/',$createdEditor['body'],$editorCodeMatch);
    $editorId=$db->query("SELECT id FROM f_manage WHERE admin='".$editorUser."'")->fetch_row()[0]??null;
    expect_check($createdEditor['status']===200 && isset($editorCodeMatch[1]) && $editorId!==null &&
        preg_match('/[A-Z]/',$editorCodeMatch[1]) && preg_match('/[2-9]/',$editorCodeMatch[1]) &&
        !str_contains($createdEditor['body'],'id="art_list"'),
        'super administrator creates editor with a one-time alphanumeric code');
    $editorCode=$editorCodeMatch[1];
    $editorCookies=[]; $editorCsrf=token($editorCookies);
    $editorLogin=['_csrf'=>$editorCsrf,'user'=>$editorUser,'password'=>$editorPassword,'admincode'=>strtolower($editorCode)];
    $wrongCode=$editorLogin; $wrongCode['admincode']=sprintf('%04d',((int)$editorCode+1)%10000);
    expect_check((request('POST','/admin/index.php?controller=admin',$editorCookies,$wrongCode)['json']['status']??'')==='n',
        'sitewide code cannot log in as editor');
    expect_check((request('POST','/admin/index.php?controller=admin',$editorCookies,$editorLogin)['json']['status']??'')==='y',
        'editor logs in with case-insensitive alphanumeric code');
    $createdSuper=request('POST','/admin/index.php?controller=account&operate=create',$cookies,
        ['_csrf'=>$newToken,'username'=>$extraSuperUser,'password'=>$editorPassword,'role'=>'super_admin']);
    preg_match('/本次生成的认证码：<strong[^>]*>([A-HJ-NP-Z2-9]{4})<\/strong>/',$createdSuper['body'],$superCodeMatch);
    $extraSuperId=$db->query("SELECT id FROM f_manage WHERE admin='".$extraSuperUser."'")->fetch_row()[0]??null;
    expect_check($createdSuper['status']===200 && isset($superCodeMatch[1]) && $extraSuperId!==null &&
        $db->query('SELECT role FROM f_manage WHERE id='.(int)$extraSuperId)->fetch_row()[0]==='super_admin',
        'super administrator can create another super administrator');
    $superCookies=[]; $superToken=token($superCookies);
    $superLogin=['_csrf'=>$superToken,'user'=>$extraSuperUser,'password'=>$editorPassword,'admincode'=>$superCodeMatch[1]];
    expect_check((request('POST','/admin/index.php?controller=admin',$superCookies,$superLogin)['json']['status']??'')==='y' &&
        request('GET','/admin/index.php?controller=account',$superCookies)['status']===200,
        'new super administrator can log in and manage accounts');
    $superToken=token($superCookies,true);
    expect_check(request('POST','/admin/index.php?controller=account&operate=delete',$cookies,
        ['_csrf'=>$newToken,'id'=>(string)$extraSuperId])['status']===403 &&
        request('POST','/admin/index.php?controller=account&operate=toggle',$cookies,
        ['_csrf'=>$newToken,'id'=>(string)$extraSuperId,'enabled'=>'0'])['status']!==303,
        'super administrator cannot be deleted or disabled');
    expect_check(request('POST','/admin/index.php?controller=account&operate=reset',$cookies,
        ['_csrf'=>$newToken,'id'=>(string)$extraSuperId,'password'=>$editorPassword])['status']===403,
        'another super administrator reset requires operator password');
    expect_check(request('POST','/admin/index.php?controller=account&operate=reset',$cookies,
        ['_csrf'=>$newToken,'id'=>(string)$extraSuperId,'password'=>$editorPassword,'operator_password'=>'wrong-password'])['status']===403,
        'another super administrator reset rejects incorrect operator password');
    $resetSuper=request('POST','/admin/index.php?controller=account&operate=reset',$cookies,
        ['_csrf'=>$newToken,'id'=>(string)$extraSuperId,'password'=>'NewPass8!','operator_password'=>$password]);
    preg_match('/本次生成的认证码：<strong[^>]*>([A-HJ-NP-Z2-9]{4})<\/strong>/',$resetSuper['body'],$resetSuperCodeMatch);
    expect_check($resetSuper['status']===200 && isset($resetSuperCodeMatch[1]) &&
        request('GET','/admin/index.php',$superCookies)['status']===302,
        'operator password permits super administrator reset and revokes session');
    $superCookies=[]; $superLogin['_csrf']=token($superCookies); $superLogin['password']='NewPass8!';
    $superLogin['admincode']=$resetSuperCodeMatch[1];
    expect_check((request('POST','/admin/index.php?controller=admin',$superCookies,$superLogin)['json']['status']??'')==='y',
        'reset super administrator logs in with new four-character code');
    $editorToken=token($editorCookies,true);
    $editorHome=request('GET','/admin/index.php',$editorCookies);
    expect_check($editorHome['status']===200 && str_contains($editorHome['body'],'<span>相关</span>') &&
        !str_contains($editorHome['body'],'<span>系统</span>') && !str_contains($editorHome['body'],'<span>模块</span>') &&
        !str_contains($editorHome['body'],'controller=cache'), 'editor menu contains business tabs only');
    $editorMain=request('GET','/admin/index.php?controller=home&operate=main',$editorCookies);
    expect_check($editorMain['status']===200 && str_contains($editorMain['body'],'你的身份：<span class="colorf60">编辑</span>') &&
        !str_contains($editorMain['body'],'网站安全信息'), 'editor dashboard omits system information');
    foreach (['module&project=article','module&project=tags','module&project=single','classify','recover','message','manage&operate=password'] as $allowed) {
        expect_check(request('GET','/admin/index.php?controller='.$allowed,$editorCookies)['status']===200,
            'editor business route allowed: '.$allowed);
    }
    expect_check(request('GET','/admin/index.php?controller=classify&operate=classify&p=article',$editorCookies)['status']===200 &&
        request('GET','/admin/index.php?controller=classify&operate=type&t=module',$editorCookies)['status']===200 &&
        request('GET','/admin/index.php?controller=classify&operate=genre&f=one',$editorCookies)['status']===200,
        'editor category form helper routes render');
    foreach (['base','modulemanage','fieldmanage&project=article','tplmanage','dbmanage&operate=dbbackup','safe','cache','signup','account'] as $denied) {
        expect_check(request('GET','/admin/index.php?controller='.$denied,$editorCookies)['status']===403,
            'editor system route denied: '.$denied);
    }
    expect_check(request('GET','/admin/index.php?controller=account&operate=add',$editorCookies)['status']===403,
        'editor cannot open account creation page');
    expect_check(request('POST','/admin/index.php?controller=dbmanage&operate=download',$editorCookies,
        ['_csrf'=>$editorToken,'backup_id'=>str_repeat('0',32)])['status']===403,
        'editor cannot request backup download by direct POST');
    expect_check(request('GET','/admin/index.php?controller=recover&project=tags',$editorCookies)['status']===403,
        'editor cannot use recycle bin with related system model');
    expect_check(request('GET','/admin/index.php?controller=module&project=manage',$editorCookies)['status']===404,
        'editor cannot address administrator table as content model');
    expect_check(request('GET','/admin/api/files.php?dir=image',$editorCookies)['status']===200 &&
        request('GET','/admin/api/files.php?dir=',$editorCookies)['status']===200,
        'editor can select media and read template filenames');
    $tagSave=request('POST','/admin/index.php?controller=module&project=tags&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'name'=>'editor_tag_'.$tag,'status'=>'1']);
    $editorTagId=(int)($tagSave['json']['id']??0) ?: null;
    expect_check(($tagSave['json']['status']??'')==='y' && $editorTagId!==null,
        'editor creates related keyword');
    $tagEdit=request('POST','/admin/index.php?controller=module&project=tags&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'id'=>(string)$editorTagId,'name'=>'editor_tag_updated_'.$tag]);
    expect_check(($tagEdit['json']['status']??'')==='y' &&
        $db->query('SELECT name FROM f_tags WHERE id='.(int)$editorTagId)->fetch_row()[0]==='editor_tag_updated_'.$tag,
        'editor updates related keyword');
    $unsafeTag='audit_'.$tag.'"><svg/onload=alert(1)>';
    $unsafeTagBatch=request('POST','/admin/index.php?controller=module&project=tags&operate=batchsave',$editorCookies,
        ['_csrf'=>$editorToken,'batch'=>$unsafeTag]);
    $unsafeTagLookup=$db->prepare('SELECT id FROM f_tags WHERE name=?');
    $unsafeTagLookup->bind_param('s',$unsafeTag); $unsafeTagLookup->execute();
    $unsafeTagId=$unsafeTagLookup->get_result()->fetch_row()[0]??null; $unsafeTagLookup->close();
    expect_check($unsafeTagBatch['status']===400 && $unsafeTagId===null,
        'editor cannot create a tag containing executable markup');
    $legacyTag='audit_legacy_'.$tag.'"><svg/onload=alert(1)>';
    $legacyTagInsert=$db->prepare('INSERT INTO f_tags (name,status) VALUES (?,1)');
    $legacyTagInsert->bind_param('s',$legacyTag); $legacyTagInsert->execute();
    $legacyUnsafeTagId=$db->insert_id; $legacyTagInsert->close();
    $legacyTagHome=request('GET','/tags.html',$editorCookies);
    expect_check($legacyTagHome['status']===200 &&
        str_contains($legacyTagHome['body'],rawurlencode($legacyTag)) &&
        !str_contains($legacyTagHome['body'],'<svg/onload=alert(1)>'),
        'existing unsafe tag names are encoded in public search links');
    $db->query('DELETE FROM f_tags WHERE id='.(int)$legacyUnsafeTagId); $legacyUnsafeTagId=null;
    $quotedTag='quoted_'.$tag.'"value'; $normalTag='normal_'.$tag; $otherTag='other_'.$tag;
    $counterInsert=$db->prepare('INSERT INTO f_tags (name,hits) VALUES (?,0)');
    foreach ([$quotedTag,$normalTag,$otherTag] as $counterName) {
        $counterInsert->bind_param('s',$counterName); $counterInsert->execute(); $counterTagIds[]=$db->insert_id;
    }
    $quotedAuthor='author_'.$tag.'"value';
    $authorInsert=$db->prepare('INSERT INTO f_author (name,hits) VALUES (?,0)');
    $authorInsert->bind_param('s',$quotedAuthor); $authorInsert->execute(); $counterAuthorId=$db->insert_id;
    $counterArticle=request('POST','/admin/index.php?controller=module&project=article&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'title'=>'counter_'.$tag,'content'=>'<p>计数回归</p>','info'=>'计数导读',
            'status'=>'1','classid'=>'0','template'=>'/article_content.html','time'=>(string)time(),
            'date'=>date('Y-m-d'),'tags'=>$quotedTag.', '.$normalTag,'author'=>$quotedAuthor,'inputer'=>'','origin'=>'']);
    $counterArticleId=(int)($counterArticle['json']['id']??0);
    if ($counterArticleId>0) { $articleIds[]=$counterArticleId; }
    expect_check(($counterArticle['json']['status']??'')==='y' && $counterArticleId>0 &&
        array_map('intval',array_column($db->query('SELECT hits FROM f_tags WHERE id IN ('.implode(',',array_map('intval',$counterTagIds)).') ORDER BY id')->fetch_all(MYSQLI_ASSOC),'hits'))===[1,1,0] &&
        (int)$db->query('SELECT hits FROM f_author WHERE id='.(int)$counterAuthorId)->fetch_row()[0]===1 &&
        $db->query('SELECT tags FROM f_article WHERE id='.$counterArticleId)->fetch_row()[0]===$quotedTag.', '.$normalTag,
        'quoted and trimmed tag/author counters update only matching rows');
    $db->query('DELETE FROM f_article WHERE id='.(int)$counterArticleId);
    foreach ($counterTagIds as $counterTagId) { $db->query('DELETE FROM f_tags WHERE id='.(int)$counterTagId); }
    $counterTagIds=[];
    $db->query('DELETE FROM f_author WHERE id='.(int)$counterAuthorId); $counterAuthorId=null;
    $categorySave=request('POST','/admin/index.php?controller=classify&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'type'=>'url','project'=>'article','name'=>'编辑外链'.$tag,
            'enname'=>'editor_'.$tag,'classid'=>'0','html'=>'https://example.test/','nav'=>'0','genre'=>'one']);
    $editorCategoryId=(int)($categorySave['json']['id']??0) ?: null;
    expect_check(($categorySave['json']['status']??'')==='y' && $editorCategoryId!==null,
        'editor creates an external-link category');
    $unsafeCategoryName='audit_category_'.$tag;
    $unsafeCategory=request('POST','/admin/index.php?controller=classify&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'type'=>'module','project'=>'article','name'=>$unsafeCategoryName,
            'enname'=>'audit"><svg/onload=alert(1)>','classid'=>'0','nav'=>'1','genre'=>'one']);
    $unsafeCategoryLookup=$db->prepare('SELECT id FROM f_classify WHERE name=?');
    $unsafeCategoryLookup->bind_param('s',$unsafeCategoryName); $unsafeCategoryLookup->execute();
    $unsafeCategoryId=$unsafeCategoryLookup->get_result()->fetch_row()[0]??null; $unsafeCategoryLookup->close();
    expect_check($unsafeCategory['status']===400 && $unsafeCategoryId===null,
        'editor cannot create a category with executable URL slug');
    $badBatchCategory=request('POST','/admin/index.php?controller=classify&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'type'=>'module','project'=>'article','name'=>'批量恶意|slug"><svg/onload=alert(1)>',
            'classid'=>'0','genre'=>'batch']);
    expect_check($badBatchCategory['status']===400,
        'batch category creation rejects executable URL slugs');
    $safeBatchCategory=request('POST','/admin/index.php?controller=classify&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'type'=>'module','project'=>'article','name'=>'批量安全|audit-safe-'.$tag,
            'classid'=>'0','intro'=>'<p>说明</p><script>alert(1)</script>','genre'=>'batch']);
    $safeBatchRow=$db->query("SELECT id,intro FROM f_classify WHERE enname='audit-safe-".$tag."'")->fetch_assoc();
    $safeBatchId=$safeBatchRow['id']??null;
    if($safeBatchId){$categoryIds[]=(int)$safeBatchId;}
    expect_check(($safeBatchCategory['json']['status']??'')==='y' && $safeBatchRow &&
        str_contains($safeBatchRow['intro'],'<p>说明</p>') && !str_contains($safeBatchRow['intro'],'<script'),
        'batch categories keep valid slugs and sanitize introductions');
    $db->query('DELETE FROM f_classify WHERE id='.(int)$safeBatchId);
    array_pop($categoryIds);
    $badExternalName='audit_external_'.$tag;
    $badExternal=request('POST','/admin/index.php?controller=classify&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'type'=>'url','project'=>'article','name'=>$badExternalName,
            'html'=>'javascript:alert(1)','classid'=>'0','genre'=>'one']);
    $badExternalRow=$db->query("SELECT id FROM f_classify WHERE name='".$badExternalName."'")->fetch_row();
    $badExternalId=$badExternalRow[0]??null;
    expect_check($badExternal['status']===400 && !$badExternalRow,
        'external category rejects javascript links');
    $badFriendName='audit_friend_'.$tag;
    $badFriend=request('POST','/admin/index.php?controller=module&project=friend&operate=batchsave',$editorCookies,
        ['_csrf'=>$editorToken,'batch'=>$badFriendName.'|javascript:alert(1)']);
    $badFriendRow=$db->query("SELECT id FROM f_friend WHERE name='".$badFriendName."'")->fetch_row();
    $unsafeFriendId=$badFriendRow[0]??null;
    expect_check($badFriend['status']===400 && !$badFriendRow,
        'friend links reject javascript URLs');
    foreach(['author','inputer','origin'] as $urlProject){
        $badUrlName='audit_'.$urlProject.'_'.$tag;
        foreach(['javascript:alert(1)','data:text/html,evil'] as $badUrl){
            $badUrlSave=request('POST','/admin/index.php?controller=module&project='.$urlProject.'&operate=save',$editorCookies,
                ['_csrf'=>$editorToken,'name'=>$badUrlName,'url'=>$badUrl,'status'=>'1']);
            expect_check($badUrlSave['status']===400,
                $urlProject.' rejects executable URL scheme on save');
        }
        expect_check((int)$db->query("SELECT COUNT(*) FROM `f_$urlProject` WHERE name='".$badUrlName."'")->fetch_row()[0]===0,
            $urlProject.' rejects executable URL before storage');
    }
    $legacyBadLink='javascript:alert(1)';
    $legacyAuthorName='audit_legacy_author_'.$tag;
    $legacyAuthorInsert=$db->prepare('INSERT INTO f_author (name,url,status) VALUES (?,?,1)');
    $legacyAuthorInsert->bind_param('ss',$legacyAuthorName,$legacyBadLink); $legacyAuthorInsert->execute();
    $legacyUnsafeAuthorId=$db->insert_id; $legacyAuthorInsert->close();
    $legacyAuthorList=request('GET','/admin/index.php?controller=module&project=author',$cookies);
    expect_check($legacyAuthorList['status']===200 && str_contains($legacyAuthorList['body'],$legacyAuthorName) &&
        !str_contains($legacyAuthorList['body'],'href="javascript:alert(1)"'),
        'admin module list neutralizes imported executable URLs');
    $legacyFriendInsert=$db->prepare('INSERT INTO f_friend (name,url,status) VALUES (?,?,1)');
    $legacyFriendInsert->bind_param('ss',$badFriendName,$legacyBadLink); $legacyFriendInsert->execute();
    $legacyUnsafeFriendId=$db->insert_id; $legacyFriendInsert->close();
    $legacyCategoryName='audit_legacy_category_'.$tag;
    $legacyCategoryInsert=$db->prepare("INSERT INTO f_classify (name,type,project,classid,nav,html) VALUES (?,'url','article',0,1,?)");
    $legacyCategoryInsert->bind_param('ss',$legacyCategoryName,$legacyBadLink); $legacyCategoryInsert->execute();
    $legacyUnsafeCategoryId=$db->insert_id; $legacyCategoryInsert->close();
    $legacyLinksHome=request('GET','/',$editorCookies);
    expect_check($legacyLinksHome['status']===200 && !str_contains($legacyLinksHome['body'],'href="javascript:alert(1)"'),
        'public navigation and friend links neutralize imported javascript URLs');
    foreach(['/admin/index.php?controller=classify','/admin/index.php?controller=classify&operate=sorts&classid=0',
        '/admin/index.php?controller=classify&operate=execution&type=save&re=1&id='.$legacyUnsafeCategoryId] as $adminCategoryRoute){
        $legacyCategoryPage=request('GET',$adminCategoryRoute,$cookies);
        expect_check($legacyCategoryPage['status']===200 && !str_contains($legacyCategoryPage['body'],'href="javascript:alert(1)"'),
            'admin category link neutralizes imported executable URL: '.$adminCategoryRoute);
    }
    $db->query('DELETE FROM f_classify WHERE id='.(int)$legacyUnsafeCategoryId); $legacyUnsafeCategoryId=null;
    $db->query('DELETE FROM f_friend WHERE id='.(int)$legacyUnsafeFriendId); $legacyUnsafeFriendId=null;
    $db->query('DELETE FROM f_author WHERE id='.(int)$legacyUnsafeAuthorId); $legacyUnsafeAuthorId=null;
    $tamperedCategory=request('POST','/admin/index.php?controller=classify&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'id'=>(string)$editorCategoryId,'type'=>'module','project'=>'manage','name'=>'bad']);
    expect_check($tamperedCategory['status']===403 &&
        $db->query('SELECT project FROM f_classify WHERE id='.(int)$editorCategoryId)->fetch_row()[0]==='article',
        'editor cannot retarget category at administrator table');
    $forgedCategory=request('POST','/admin/index.php?controller=classify&operate=save',$cookies,
        ['_csrf'=>$newToken,'id'=>(string)$editorCategoryId,'name'=>'允许修改'.$tag,
            'topid'=>'9999','classid'=>'9999','sort'=>'9999','time'=>'1','unknown_field'=>'x']);
    $categoryAfterForge=$db->query('SELECT name,topid,classid,sort,time FROM f_classify WHERE id='.(int)$editorCategoryId)->fetch_assoc();
    expect_check(($forgedCategory['json']['status']??'')==='y' &&
        $categoryAfterForge['name']==='允许修改'.$tag && (int)$categoryAfterForge['topid']!==9999 &&
        (int)$categoryAfterForge['classid']!==9999 && (int)$categoryAfterForge['sort']!==9999 &&
        (int)$categoryAfterForge['time']!==1,
        'category save rejects hierarchy and internal field overposting');
    $badCategoryBatch=request('POST','/admin/index.php?controller=classify&operate=batch',$cookies,
        ['_csrf'=>$newToken,'classify'=>['1 OR 1=1']]);
    expect_check($badCategoryBatch['status']===400 &&
        $db->query('SELECT id FROM f_classify WHERE id='.(int)$editorCategoryId)->num_rows===1,
        'category batch rejects malformed IDs before mutation');
    if ($db->query("SHOW TABLES LIKE 'f_signup_base'")->num_rows) {
        throw new RuntimeException('Refusing to replace an existing signup settings table.');
    }
    $db->query('CREATE TABLE f_signup_base (id int PRIMARY KEY, name varchar(100), phone varchar(100), qq varchar(100), pnumber varchar(100), fmail text, smail text, time int, secret varchar(100)) ENGINE=InnoDB');
    $signupFixtureCreated=true;
    $db->query("INSERT INTO f_signup_base (id,name,secret,time) VALUES (1,'original','keep',100)");
    $signupSave=request('POST','/admin/index.php?controller=signup&operate=save',$cookies,
        ['_csrf'=>$newToken,'name'=>'updated_'.$tag,'secret'=>'forged','time'=>'1','unknown_field'=>'x']);
    $signupRow=$db->query('SELECT name,secret,time FROM f_signup_base WHERE id=1')->fetch_assoc();
    expect_check(($signupSave['json']['status']??'')==='y' && $signupRow['name']==='updated_'.$tag &&
        $signupRow['secret']==='keep' && (int)$signupRow['time']!==1,
        'signup settings update only allowed fields in the unique settings row');
    $db->query("INSERT INTO f_signup_base (id,name,secret,time) VALUES (2,'second','keep',100)");
    $ambiguousSignup=request('POST','/admin/index.php?controller=signup&operate=save',$cookies,
        ['_csrf'=>$newToken,'name'=>'must-not-write']);
    expect_check($ambiguousSignup['status']===409 &&
        (int)$db->query("SELECT COUNT(*) FROM f_signup_base WHERE name='must-not-write'")->fetch_row()[0]===0,
        'signup settings refuse ambiguous multiple rows');
    $db->query('DELETE FROM f_signup_base WHERE id=2');
    $db->query('ALTER TABLE f_signup_base DROP PRIMARY KEY');
    $unkeyedSignup=request('POST','/admin/index.php?controller=signup&operate=save',$cookies,
        ['_csrf'=>$newToken,'name'=>'must-not-write']);
    expect_check($unkeyedSignup['status']===409 &&
        $db->query('SELECT name FROM f_signup_base WHERE id=1')->fetch_row()[0]==='updated_'.$tag,
        'signup settings refuse a row without a stable primary key');
    $db->query('DROP TABLE f_signup_base');
    $signupFixtureCreated=false;
    $articleSave=request('POST','/admin/index.php?controller=module&project=article&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'title'=>'editor_article_'.$tag,'content'=>'<p>编辑正文</p>','info'=>'编辑导读',
            'status'=>'1','classid'=>'0','template'=>'/article_content.html','time'=>(string)time(),
            'date'=>date('Y-m-d'),'tags'=>'','author'=>'','inputer'=>'','origin'=>'']);
    $editorArticleId=(int)($articleSave['json']['id']??0) ?: null;
    expect_check(($articleSave['json']['status']??'')==='y' && $editorArticleId!==null,
        'editor creates article content');
    $invalidBatch=request('POST','/admin/index.php?controller=module&project=article&operate=batch',$editorCookies,
        ['_csrf'=>$editorToken,'method'=>'recover','parameter'=>'','record'=>(string)$editorArticleId]);
    expect_check($invalidBatch['status']===400 &&
        (int)$db->query('SELECT status FROM f_article WHERE id='.(int)$editorArticleId)->fetch_row()[0]===1,
        'editor batch rejects scalar record without changing article');
    $invalidRecords=[(string)$editorArticleId, [[$editorArticleId]], ['1 OR 1=1'], ['0'], array_fill(0,501,(string)$editorArticleId)];
    foreach ($invalidRecords as $record) {
        $badBatch=request('POST','/admin/index.php?controller=module&project=article&operate=batch',$editorCookies,
            ['_csrf'=>$editorToken,'method'=>'recover','record'=>$record]);
        expect_check($badBatch['status']===400 &&
            (int)$db->query('SELECT status FROM f_article WHERE id='.(int)$editorArticleId)->fetch_row()[0]===1,
            'invalid article batch record rejected without mutation');
    }
    $forgedAttribute=request('POST','/admin/index.php?controller=module&project=article&operate=batch',$editorCookies,
        ['_csrf'=>$editorToken,'method'=>'attrib','parameter'=>'status','record'=>[(string)$editorArticleId]]);
    expect_check($forgedAttribute['status']===400 &&
        (int)$db->query('SELECT status FROM f_article WHERE id='.(int)$editorArticleId)->fetch_row()[0]===1,
        'batch attribute cannot address arbitrary columns');
    $legalAttribute=request('POST','/admin/index.php?controller=module&project=article&operate=batch',$editorCookies,
        ['_csrf'=>$editorToken,'method'=>'attrib','parameter'=>'attrib_j','record'=>[(string)$editorArticleId]]);
    expect_check(($legalAttribute['json']['status']??'')==='y' &&
        (int)$db->query('SELECT attrib_j FROM f_article WHERE id='.(int)$editorArticleId)->fetch_row()[0]===1,
        'legal article batch attribute update succeeds');
    $batchRecover=request('POST','/admin/index.php?controller=module&project=article&operate=batch',$editorCookies,
        ['_csrf'=>$editorToken,'method'=>'recover','record'=>[(string)$editorArticleId,(string)$editorArticleId]]);
    expect_check(($batchRecover['json']['status']??'')==='y' &&
        (int)$db->query('SELECT status FROM f_article WHERE id='.(int)$editorArticleId)->fetch_row()[0]===-1,
        'legal article batch deduplicates IDs and moves only target to recycle bin');
    expect_check(request('POST','/admin/index.php?controller=recover&project=article&operate=recoverone&id='.$editorArticleId,
        $editorCookies,['_csrf'=>$editorToken])['status']===200 &&
        (int)$db->query('SELECT status FROM f_article WHERE id='.(int)$editorArticleId)->fetch_row()[0]===1,
        'article from batch operation restores successfully');
    $articleEdit=request('POST','/admin/index.php?controller=module&project=article&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'id'=>(string)$editorArticleId,'title'=>'editor_updated_'.$tag,
            'content'=>'<p>修改正文</p>','info'=>'修改导读']);
    expect_check(($articleEdit['json']['status']??'')==='y' &&
        $db->query('SELECT title FROM f_article WHERE id='.(int)$editorArticleId)->fetch_row()[0]==='editor_updated_'.$tag,
        'editor updates article content');
    $forgedArticle=request('POST','/admin/index.php?controller=module&project=article&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'id'=>(string)$editorArticleId,'title'=>'allowed_'.$tag,
            'hits'=>'999999','html'=>'/forged.html','time'=>'1','password'=>'injected','unknown_field'=>'x']);
    $articleAfterForge=$db->query('SELECT title,hits,html,time FROM f_article WHERE id='.(int)$editorArticleId)->fetch_assoc();
    expect_check(($forgedArticle['json']['status']??'')==='y' &&
        $articleAfterForge['title']==='allowed_'.$tag && (int)$articleAfterForge['hits']!==999999 &&
        $articleAfterForge['html']!=='/forged.html' && (int)$articleAfterForge['time']!==1,
        'article save ignores client-owned and unknown fields while keeping editable title');
    $db->query("INSERT INTO f_message (title,content,status,time) VALUES ('editor_msg_$tag','hello',0,UNIX_TIMESTAMP())");
    $editorMessageId=$db->insert_id;
    foreach ([[$editorMessageId=>'x'], [[$editorMessageId]], ['1 OR 1=1'], ['0'], array_fill(0,501,(string)$editorMessageId)] as $record) {
        foreach ([['message',$editorCookies,$editorToken],['signup',$cookies,$newToken]] as [$controller,$actorCookies,$actorToken]) {
            $badDelete=request('POST','/admin/index.php?controller='.$controller.'&operate=deletebatch',$actorCookies,
                ['_csrf'=>$actorToken,'record'=>$record]);
            expect_check($badDelete['status']===400 &&
                $db->query('SELECT id FROM f_message WHERE id='.(int)$editorMessageId)->num_rows===1,
                'invalid '.$controller.' batch record rejected without mutation');
        }
    }
    $reply=request('POST','/admin/index.php?controller=message&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'id'=>(string)$editorMessageId,'reply'=>'编辑回复','status'=>'1']);
    expect_check(($reply['json']['status']??'')==='y' &&
        $db->query('SELECT reply FROM f_message WHERE id='.(int)$editorMessageId)->fetch_row()[0]==='编辑回复',
        'editor replies to visitor message');
    $forgedReply=request('POST','/admin/index.php?controller=message&operate=save',$editorCookies,
        ['_csrf'=>$editorToken,'id'=>(string)$editorMessageId,'reply'=>'二次回复','time'=>'1','unknown_field'=>'x']);
    $messageAfterForge=$db->query('SELECT reply,time FROM f_message WHERE id='.(int)$editorMessageId)->fetch_assoc();
    expect_check(($forgedReply['json']['status']??'')==='y' && $messageAfterForge['reply']==='二次回复' &&
        (int)$messageAfterForge['time']!==1,
        'message reply ignores submitted creation time and unknown fields');
    expect_check(request('POST','/admin/index.php?controller=module&project=article&operate=recoverone&id='.$editorArticleId,
        $editorCookies,['_csrf'=>$editorToken])['status']===200 &&
        (int)$db->query('SELECT status FROM f_article WHERE id='.(int)$editorArticleId)->fetch_row()[0]===-1,
        'editor moves article to recycle bin');
    expect_check(request('POST','/admin/index.php?controller=recover&project=article&operate=recoverone&id='.$editorArticleId,
        $editorCookies,['_csrf'=>$editorToken])['status']===200 &&
        (int)$db->query('SELECT status FROM f_article WHERE id='.(int)$editorArticleId)->fetch_row()[0]===1,
        'editor restores article from recycle bin');
    $picture=imagecreatetruecolor(2,2); ob_start(); imagepng($picture); $imageBytes=ob_get_clean(); imagedestroy($picture);
    $editorUpload=upload($editorCookies,$editorToken,'editor.png',$imageBytes);
    expect_check($editorUpload['status']===200 && ($editorUpload['json']['error']??null)===0 &&
        str_starts_with($editorUpload['json']['url']??'','/upload/image/'),
        'editor uploads content image');
    $files[]=dirname(__DIR__).$editorUpload['json']['url'];
    expect_check(request('POST','/admin/index.php?controller=classify&operate=delete&id='.$editorCategoryId,
        $editorCookies,['_csrf'=>$editorToken])['status']===200 &&
        $db->query('SELECT id FROM f_classify WHERE id='.(int)$editorCategoryId)->num_rows===0,
        'editor deletes external-link category');
    $editorCategoryId=null;
    expect_check(request('POST','/admin/index.php?controller=message&operate=deleteone&id='.$editorMessageId,
        $editorCookies,['_csrf'=>$editorToken])['status']===200 &&
        $db->query('SELECT id FROM f_message WHERE id='.(int)$editorMessageId)->num_rows===0,
        'editor deletes visitor message');
    $editorMessageId=null;
    $db->query("INSERT INTO f_message (title,content,status,time) VALUES ('batch_msg_$tag','hello',0,UNIX_TIMESTAMP())");
    $batchMessageId=$db->insert_id;
    $batchDelete=request('POST','/admin/index.php?controller=message&operate=deletebatch',$editorCookies,
        ['_csrf'=>$editorToken,'record'=>[(string)$batchMessageId]]);
    expect_check(($batchDelete['json']['status']??'')==='y' &&
        $db->query('SELECT id FROM f_message WHERE id='.(int)$batchMessageId)->num_rows===0,
        'legal message batch deletes only selected record');
    $batchMessageId=null;
    expect_check(request('POST','/admin/index.php?controller=module&project=tags&operate=deleteone&id='.$editorTagId,$editorCookies,
        ['_csrf'=>$editorToken])['status']===200 &&
        $db->query('SELECT id FROM f_tags WHERE id='.(int)$editorTagId)->num_rows===0,
        'editor deletes related keyword');
    $editorTagId=null;
    expect_check(request('POST','/admin/index.php?controller=account&operate=toggle',$cookies,
        ['_csrf'=>$newToken,'id'=>(string)$editorId,'enabled'=>'0'])['status']===303 &&
        request('GET','/admin/index.php',$editorCookies)['status']===302,
        'disabling editor revokes active session');
    expect_check(request('POST','/admin/index.php?controller=account&operate=toggle',$cookies,
        ['_csrf'=>$newToken,'id'=>(string)$editorId,'enabled'=>'1'])['status']===303,
        'super administrator re-enables editor');
    $editorCookies=[]; $editorLogin['_csrf']=token($editorCookies);
    expect_check((request('POST','/admin/index.php?controller=admin',$editorCookies,$editorLogin)['json']['status']??'')==='y',
        're-enabled editor can log in again');
    $editorToken=token($editorCookies,true);
    expect_check(request('POST','/admin/index.php?controller=account&operate=delete',$editorCookies,
        ['_csrf'=>$editorToken,'id'=>(string)$editorId])['status']===403,
        'editor cannot delete accounts');
    $resetEditor=request('POST','/admin/index.php?controller=account&operate=reset',$cookies,
        ['_csrf'=>$newToken,'id'=>(string)$editorId,'password'=>$editorPassword]);
    preg_match('/本次生成的认证码：<strong[^>]*>([A-HJ-NP-Z2-9]{4})<\/strong>/',$resetEditor['body'],$resetEditorCodeMatch);
    expect_check($resetEditor['status']===200 && isset($resetEditorCodeMatch[1]) &&
        request('GET','/admin/index.php',$editorCookies)['status']===302,
        'credential reset revokes editor sessions');
    $editorCookies=[]; $editorLogin['_csrf']=token($editorCookies); $editorLogin['admincode']=$resetEditorCodeMatch[1];
    expect_check((request('POST','/admin/index.php?controller=admin',$editorCookies,$editorLogin)['json']['status']??'')==='y',
        'editor logs in with reset four-character code');
    expect_check(request('POST','/admin/index.php?controller=account&operate=delete',$cookies,
        ['_csrf'=>$newToken,'id'=>(string)$editorId])['status']===303 &&
        $db->query('SELECT id FROM f_manage WHERE id='.(int)$editorId)->num_rows===0 &&
        request('GET','/admin/index.php',$editorCookies)['status']===302,
        'super administrator deletes editor account and revokes session');
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
    $fieldStatusBefore=$db->query('SELECT status FROM f_module_field WHERE id='.(int)$customFieldSave['json']['id'])->fetch_row()[0];
    $badFieldStatus=request('POST',$fieldRoute.'&operate=enable&id='.$customFieldSave['json']['id'].'&status=2',
        $cookies,['_csrf'=>$newToken]);
    $fieldStatusAfter=$db->query('SELECT status FROM f_module_field WHERE id='.(int)$customFieldSave['json']['id'])->fetch_row()[0];
    $db->query('UPDATE f_module_field SET status='.(int)$fieldStatusBefore.' WHERE id='.(int)$customFieldSave['json']['id']);
    expect_check($badFieldStatus['status']===400 && $fieldStatusAfter===$fieldStatusBefore,
        'field state rejects values outside zero and one');
    expect_check($db->query("SHOW COLUMNS FROM `f_$moduleProject` LIKE '".$customFieldData['name']."'")->num_rows===1,'custom field creation updates the declared module table');
    $export=request('GET','/admin/index.php?controller=modulemanage&operate=elicit&id='.$moduleFixtureId,$cookies);
    $package=json_decode($export['body'],true);
    expect_check($export['status']===200 && ($package['format']??'')==='fengcms-model-v1' &&
        ($package['source_project']??'')===$moduleProject && count($package['fields'])>=10,
        'model export returns structured portable definition');
    $modelRoute='/admin/index.php?controller=modulemanage';
    $modelLoad=request('GET',$modelRoute.'&operate=load',$cookies);
    expect_check($modelLoad['status']===200 && str_contains($modelLoad['body'],'public/jquery/validform/css.css') &&
        str_contains($modelLoad['body'],'enctype="multipart/form-data" class="form"') &&
        str_contains($modelLoad['body'],'name="package_json" class="inputxt"'),
        'model import form uses common admin input styles');
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
    $baseForge=request('POST','/admin/index.php?controller=base&operate=save',$cookies,
        ['_csrf'=>$newToken,'name'=>'base_safe_'.$tag,'time'=>'1','unlisted'=>'injected','url_type'=>'1']);
    $baseAfterForge=$db->query('SELECT name,time FROM f_base LIMIT 1')->fetch_assoc();
    expect_check(($baseForge['json']['status']??'')==='y' && $baseAfterForge['name']==='base_safe_'.$tag &&
        (string)$baseAfterForge['time']!=='1','site settings ignore unknown and client-owned fields');
    $extraBase=$db->prepare('INSERT INTO f_base (name,copyright) VALUES (?,?)');
    $extraBaseName='extra_'.$tag; $extraBase->bind_param('ss',$extraBaseName,$extraBaseCopyright);
    $extraBase->execute(); $extraBase->close();
    $ambiguousBase=request('POST','/admin/index.php?controller=base&operate=save',$cookies,
        ['_csrf'=>$newToken,'name'=>'should_not_write_'.$tag,'url_type'=>'1']);
    $ambiguousBaseRows=$db->query("SELECT COUNT(*) FROM f_base WHERE name='should_not_write_".$tag."'")->fetch_row()[0];
    $deleteExtra=$db->prepare('DELETE FROM f_base WHERE copyright=?');
    $deleteExtra->bind_param('s',$extraBaseCopyright); $deleteExtra->execute(); $deleteExtra->close();
    expect_check($ambiguousBase['status']===409 && (int)$ambiguousBaseRows===0,
        'site settings refuse an ambiguous multi-row table');
    expect_check(request('GET','/tags.html',$cookies)['status']===200,'tag cloud URL works in pseudo-static mode');
    $copyrightSave=request('POST','/admin/index.php?controller=base&operate=save',$cookies,['_csrf'=>$newToken,'copyright'=>$attack,'url_type'=>'1']);
    $copyright=$db->query('SELECT copyright FROM f_base LIMIT 1')->fetch_assoc()['copyright'];
    expect_check(($copyrightSave['json']['status']??'')==='y' && !str_contains($copyright,'<script') && str_contains($copyright,'<strong>加粗</strong>'), 'copyright HTML uses the server sanitizer');
    $importedCopyright='<p>保留段落</p><script>window.auditCopyright=1</script>';
    $importedCopyrightSave=$db->prepare('UPDATE f_base SET copyright=?');
    $importedCopyrightSave->bind_param('s',$importedCopyright); $importedCopyrightSave->execute();
    $importedCopyrightPage=request('GET','/',$cookies);
    expect_check($importedCopyrightPage['status']===200 &&
        !str_contains($importedCopyrightPage['body'],'window.auditCopyright=1') &&
        str_contains($importedCopyrightPage['body'],'保留段落') &&
        $db->query('SELECT copyright FROM f_base LIMIT 1')->fetch_row()[0]===$importedCopyright,
        'imported copyright is cleaned only for rendering and not rewritten in storage');
    expect_check(cms_clean_copyright('<p>安全内容</p><script>bad()</script>')==='<p>安全内容</p>' &&
        cms_clean_copyright(str_repeat('X',2*1024*1024+1))==='',
        'copyright rendering filters scripts and degrades safely for oversized custom fields');
    $restoreCopyright=$db->prepare('UPDATE f_base SET copyright=?');
    $restoreCopyright->bind_param('s',$copyright); $restoreCopyright->execute();

    expect_check(request('GET','/index.html',$cookies)['status']===200,'rewrite mode renders homepage');
    request('POST','/admin/index.php?controller=base&operate=save',$cookies,['_csrf'=>$newToken,'name'=>$originalName,'url_type'=>'0']);
    expect_check(request('GET','/tags.html',$cookies)['status']===200 && request('GET','/?controller=tags',$cookies)['status']===200,'tag cloud URLs work in dynamic mode');
    foreach (['home&operate=main','base','manage&operate=password','modulemanage','module&project=article','classify','message','tplmanage','dbmanage&operate=dbmanages'] as $route) {
        $page=request('GET','/admin/index.php?controller='.$route,$cookies);
        expect_check($page['status']===200 && str_contains($page['body'],'<html'),'admin screen renders: '.$route);
    }
    $safeStarted=microtime(true);
    $safePage=request('GET','/admin/index.php?controller=safe',$cookies);
    expect_check($safePage['status']===200 && microtime(true)-$safeStarted<5 &&
        str_contains($safePage['body'],'<div class="safe">'),
        'online safety lookup has a bounded fallback response');
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
    expect_check(upload($cookies,$newToken,'shell.php.png',$png)['status']===400,
        'script double extension is rejected even when image bytes are valid');
    expect_check(upload($cookies,$newToken,'pixel.png',$png,'../image')['status']===400,
        'upload directory traversal is rejected');
    expect_check(upload($cookies,$newToken,'fake.pdf','plain text','file')['status']===400,
        'attachment extension must match detected MIME');
    $hugePng=substr_replace($png,pack('NN',50000,50000),16,8);
    $hugePng=substr_replace($hugePng,hash('crc32b',substr($hugePng,12,17),true),29,4);
    expect_check(upload($cookies,$newToken,'huge.png',$hugePng)['status']===400,
        'image pixel count is bounded independently of byte size');
    expect_check(in_array(upload($cookies,$newToken,'oversize.png',str_repeat('x',10*1024*1024+1))['status'],[400,413],true),
        'oversize upload is rejected');
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
    $ipBefore=$_SERVER['REMOTE_ADDR']??null; $forwardedBefore=$_SERVER['HTTP_X_FORWARDED_FOR']??null;
    $_SERVER['REMOTE_ADDR']='192.0.2.10'; $_SERVER['HTTP_X_FORWARDED_FOR']='198.51.100.99';
    $reportedIp=getIP();
    if($ipBefore===null){unset($_SERVER['REMOTE_ADDR']);}else{$_SERVER['REMOTE_ADDR']=$ipBefore;}
    if($forwardedBefore===null){unset($_SERVER['HTTP_X_FORWARDED_FOR']);}else{$_SERVER['HTTP_X_FORWARDED_FOR']=$forwardedBefore;}
    expect_check($reportedIp==='192.0.2.10','IP helper ignores untrusted forwarded headers');
    $legacySlugUrl=url('/article_a&controller=home_7.html');
    expect_check(!str_contains($legacySlugUrl,'&controller=home') && str_contains($legacySlugUrl,'%26controller%3Dhome'),
        'dynamic URL conversion encodes imported category slugs');
    $Message='<script>auditError()</script>'; $File='" onload="auditError()'; $Line='1"><svg/onload=alert(1)>';
    foreach(['app/error/error.php','admin/app/error/error.php'] as $errorTemplate){
        ob_start(); include ROOT_PATH.'/'.$errorTemplate; $errorPage=ob_get_clean();
        expect_check(!str_contains($errorPage,'<script>auditError()') &&
            str_contains($errorPage,'&lt;script&gt;auditError()&lt;/script&gt;') &&
            !str_contains($errorPage,'<svg/onload='),
            'legacy error page escapes diagnostic fields: '.$errorTemplate);
    }
    require_once ROOT_PATH.'/admin/app/model/fieldModel.php';
    expect_check(!method_exists(fieldModel::class,'save'),
        'unused field model cannot update the whole metadata table');
    $templateSwitch=getenv('FENGCMS_DISABLE_TEMPLATE_WRITE');
    putenv('FENGCMS_DISABLE_TEMPLATE_WRITE=1');
    $templateReadOnly=!cms_template_write_enabled();
    if($templateSwitch===false){putenv('FENGCMS_DISABLE_TEMPLATE_WRITE');}
    else{putenv('FENGCMS_DISABLE_TEMPLATE_WRITE='.$templateSwitch);}
    expect_check($templateReadOnly && cms_template_write_enabled(),
        'site owner can disable online template writes without hiding template reads');
    $queryBefore=$_GET; $selfBefore=$_SERVER['PHP_SELF']??null;
    $_GET=['page'=>'1','probe'=>'x" onfocus="alert(1)']; $_SERVER['PHP_SELF']='/index.php';
    $wapPage=new page(20,10,['pagetype'=>'wap']);
    $wapJump=(new ReflectionMethod(page::class,'getjumpstr'))->invoke($wapPage,'input');
    $_GET=$queryBefore;
    if($selfBefore===null){unset($_SERVER['PHP_SELF']);}else{$_SERVER['PHP_SELF']=$selfBefore;}
    expect_check(str_contains($wapJump,'&quot;') && !str_contains($wapJump,'value="x" onfocus='),
        'WAP page jump fields escape query values');
    foreach (['template','admin/template'] as $directory) {
        $legacySyntax=true;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_PATH.'/'.$directory)) as $file) {
            if ($file->isFile() && $file->getExtension()==='html' && str_contains(file_get_contents($file->getPathname()),'{raw ')) { $legacySyntax=false; }
        }
        expect_check($legacySyntax,'bundled templates retain original variable syntax: '.$directory);
    }

    $_SESSION['authnum']='abcd'; $_SESSION['authnum_at']=time();
    $wrongMessage=M('message')->save(['vcode'=>'zzzz']);
    expect_check(($wrongMessage['status']??'')==='c' && !isset($_SESSION['authnum']),
        'incorrect message captcha is consumed immediately');
    $_SESSION['authnum']='abcd'; $_SESSION['authnum_at']=time();
    expect_check(cms_captcha_verify('AbCd') && $_SESSION['authnum']==='abcd',
        'correct asynchronous captcha check keeps the code for message submission');
    $message=M('message')->save(['vcode'=>'abcd','title'=>'message_'.$tag,'content'=>'<b data-probe="message">hello</b>','name'=>'visitor','status'=>'1','reply'=>'injected','time'=>'1']);
    $messageId=$message['id']??null;
    $savedMessage=$db->query('SELECT * FROM f_message WHERE id='.(int)$messageId)->fetch_assoc();
    expect_check(($message['status']??'')==='y' && (int)$savedMessage['status']===0 && $savedMessage['reply']===null && (int)$savedMessage['time']>1,'public message cannot set publication, reply or time');
    $messagePage=request('GET','/admin/index.php?controller=message',$cookies);
    expect_check(str_contains($messagePage['body'],'&lt;b data-probe=&quot;message&quot;&gt;') && !str_contains($messagePage['body'],'<b data-probe="message">'),'visitor message is escaped in admin');
    $unansweredMessages=request('GET','/admin/index.php?controller=message&status=0',$cookies);
    expect_check($unansweredMessages['status']===200 && str_contains($unansweredMessages['body'],'message_'.$tag),
        'unanswered message filter still lists unpublished visitor messages');
    require_once dirname(__DIR__).'/system/core/email.php';
    $smtpFixture=new smtp('smtp.example.test',2525,false,'test-user','test-pass');
    expect_check($smtpFixture->relay_host==='smtp.example.test' && $smtpFixture->smtp_port===2525 &&
        $smtpFixture->user==='test-user','legacy SMTP helper initializes on PHP 8');
    expect_check($smtpFixture->strip_comment('User (outer (inner)) <user@example.test>')==='User  <user@example.test>' &&
        $smtpFixture->get_address('User <user@example.test>')==='user@example.test',
        'legacy SMTP address helpers no longer call removed ereg functions');
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
    $db->query("CREATE VIEW `$viewFixture` AS SELECT id,name FROM `f_view_field`");
    $beforeRejected=glob($backupDir.'/*.json') ?: [];
    $multipleViews=request('POST',$backupRoute.'&operate=create',$cookies,['_csrf'=>$newToken]);
    expect_check($multipleViews['status']===500 &&
        (glob($backupDir.'/*.json') ?: [])===$beforeRejected &&
        str_contains($multipleViews['body'],'多个视图'), 'dependent views are refused before publishing a backup');
    $db->query("DROP VIEW `$viewFixture`");
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
    $importPreviewRoute=$backupRoute.'&operate=previewimport';
    expect_check(request('GET',$importPreviewRoute,$cookies)['status']===405,'database import preview requires POST');
    $importAnonymous=[];
    expect_check(request('POST',$importPreviewRoute,$importAnonymous,['_csrf'=>$newToken,'backup_id'=>$backupId])['status']===401,
        'anonymous database import preview rejected');
    expect_check(request('POST',$importPreviewRoute,$cookies,['backup_id'=>$backupId])['status']===403,
        'database import preview requires CSRF');
    $importPreview=request('POST',$importPreviewRoute,$cookies,['_csrf'=>$newToken,'backup_id'=>$backupId]);
    expect_check($importPreview['status']===200 && str_contains($importPreview['body'],'导入预览') &&
        str_contains($importPreview['body'],'确认导入'),'verified backup can be previewed for import');
    expect_check(request('POST',$backupRoute.'&operate=runimport',$cookies,
        ['_csrf'=>$newToken,'nonce'=>'invalid','confirm'=>'1'])['status']===400,
        'database import rejects an invalid preview nonce');
    $beforeUploads=glob($backupDir.'/.import-*.sql') ?: [];
    $uploadBoundary='fengcms'.bin2hex(random_bytes(8));
    $uploadBody="--$uploadBoundary\r\nContent-Disposition: form-data; name=\"_csrf\"\r\n\r\n$newToken\r\n";
    $uploadBody.="--$uploadBoundary\r\nContent-Disposition: form-data; name=\"backup_file\"; filename=\"backup.sql\"\r\nContent-Type: application/octet-stream\r\n\r\n";
    $uploadBody.=file_get_contents($backupDir.'/'.$backupId.'.sql')."\r\n--$uploadBoundary--\r\n";
    $uploadedPreview=request('POST',$importPreviewRoute,$cookies,$uploadBody,
        ['Content-Type'=>'multipart/form-data; boundary='.$uploadBoundary]);
    expect_check($uploadedPreview['status']===200 && str_contains($uploadedPreview['body'],'上传的 SQL 备份') &&
        count(glob($backupDir.'/.import-*.sql') ?: [])===count($beforeUploads)+1,
        'administrator can upload and preview a valid CMS SQL backup');
    $boundary='fengcms'.bin2hex(random_bytes(8));
    $multipart="--$boundary\r\nContent-Disposition: form-data; name=\"_csrf\"\r\n\r\n$newToken\r\n";
    $multipart.="--$boundary\r\nContent-Disposition: form-data; name=\"backup_file\"; filename=\"bad.sql\"\r\nContent-Type: application/octet-stream\r\n\r\nDROP TABLE f_base;\r\n--$boundary--\r\n";
    expect_check(request('POST',$importPreviewRoute,$cookies,$multipart,
        ['Content-Type'=>'multipart/form-data; boundary='.$boundary])['status']===400 &&
        (glob($backupDir.'/.import-*.sql') ?: [])===$beforeUploads,'arbitrary SQL upload rejected and cleaned');
    $wrongHeader=str_replace('-- FengCMS logical backup; restore into an empty database only.',
        '-- FengCMS logical backup v999.',file_get_contents($backupDir.'/'.$backupId.'.sql'));
    $wrongFormat="--$boundary\r\nContent-Disposition: form-data; name=\"_csrf\"\r\n\r\n$newToken\r\n";
    $wrongFormat.="--$boundary\r\nContent-Disposition: form-data; name=\"backup_file\"; filename=\"wrong.sql\"\r\nContent-Type: application/octet-stream\r\n\r\n$wrongHeader\r\n--$boundary--\r\n";
    expect_check(request('POST',$importPreviewRoute,$cookies,$wrongFormat,
        ['Content-Type'=>'multipart/form-data; boundary='.$boundary])['status']===400 &&
        (glob($backupDir.'/.import-*.sql') ?: [])===$beforeUploads,
        'database import rejects unsupported backup format marker');
    $oversizeImport="--$boundary\r\nContent-Disposition: form-data; name=\"_csrf\"\r\n\r\n$newToken\r\n";
    $oversizeImport.="--$boundary\r\nContent-Disposition: form-data; name=\"backup_file\"; filename=\"large.sql\"\r\nContent-Type: application/octet-stream\r\n\r\n";
    $oversizeImport.=str_repeat('x',8*1024*1024+1)."\r\n--$boundary--\r\n";
    expect_check(request('POST',$importPreviewRoute,$cookies,$oversizeImport,
        ['Content-Type'=>'multipart/form-data; boundary='.$boundary])['status']===400 &&
        (glob($backupDir.'/.import-*.sql') ?: [])===$beforeUploads,
        'database import rejects packages above the backend limit');
    $anonymous=[];
    expect_check(request('POST',$backupRoute.'&operate=download',$anonymous,['_csrf'=>$newToken,'backup_id'=>$backupId])['status']===401,
        'anonymous backup download rejected');
    expect_check(request('POST',$backupRoute.'&operate=download',$cookies,['backup_id'=>$backupId])['status']===403,
        'backup download requires CSRF');
    expect_check(request('POST',$backupRoute.'&operate=download',$cookies,['_csrf'=>$newToken,'backup_id'=>'../config'])['status']===400,
        'backup download rejects traversal');
    expect_check(request('POST',$backupRoute.'&operate=download',$cookies,
        ['_csrf'=>$newToken,'backup_id'=>str_repeat('a',32)])['status']===404,
        'backup download rejects a forged but well-formed ID');
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
    $newPassword='NewPass8!';
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
    $captchaRateCookies=[];
    $captchaRateToken=visitor_token($captchaRateCookies);
    for($i=0;$i<31;$i++){
        $captchaRate=request('POST','/?controller=home&operate=vccore',$captchaRateCookies,
            ['param'=>'zzzz'],['X-CSRF-Token'=>$captchaRateToken]);
    }
    expect_check($captchaRate['status']===429,'captcha guesses are limited per remote address');
    echo "All $passed regression checks passed.\n";
} finally {
    if ($signupFixtureCreated) { $db->query('DROP TABLE f_signup_base'); }
    $db->query("DROP VIEW IF EXISTS `$viewFixture`");
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
    foreach ([$testId,$legacyId,$editorId,$extraSuperId] as $id) { if ($id) { $db->query('DELETE FROM f_manage WHERE id='.(int)$id); } }
    foreach ($articleIds as $id) { $db->query('DELETE FROM f_article WHERE id='.(int)$id); }
    if ($editorArticleId) { $db->query('DELETE FROM f_article WHERE id='.(int)$editorArticleId); }
    if ($editorCategoryId) { $db->query('DELETE FROM f_classify WHERE id='.(int)$editorCategoryId); }
    if ($unsafeCategoryId) { $db->query('DELETE FROM f_classify WHERE id='.(int)$unsafeCategoryId); }
    if ($legacyUnsafeCategoryId) { $db->query('DELETE FROM f_classify WHERE id='.(int)$legacyUnsafeCategoryId); }
    if ($legacyUnsafeAuthorId) { $db->query('DELETE FROM f_author WHERE id='.(int)$legacyUnsafeAuthorId); }
    if ($badExternalId) { $db->query('DELETE FROM f_classify WHERE id='.(int)$badExternalId); }
    if ($editorTagId) { $db->query('DELETE FROM f_tags WHERE id='.(int)$editorTagId); }
    if ($unsafeTagId) { $db->query('DELETE FROM f_tags WHERE id='.(int)$unsafeTagId); }
    if ($legacyUnsafeTagId) { $db->query('DELETE FROM f_tags WHERE id='.(int)$legacyUnsafeTagId); }
    if ($unsafeFriendId) { $db->query('DELETE FROM f_friend WHERE id='.(int)$unsafeFriendId); }
    if ($legacyUnsafeFriendId) { $db->query('DELETE FROM f_friend WHERE id='.(int)$legacyUnsafeFriendId); }
    foreach ($counterTagIds as $counterTagId) { $db->query('DELETE FROM f_tags WHERE id='.(int)$counterTagId); }
    if ($counterAuthorId) { $db->query('DELETE FROM f_author WHERE id='.(int)$counterAuthorId); }
    if ($editorMessageId) { $db->query('DELETE FROM f_message WHERE id='.(int)$editorMessageId); }
    if ($batchMessageId) { $db->query('DELETE FROM f_message WHERE id='.(int)$batchMessageId); }
    if ($messageId) { $db->query('DELETE FROM f_message WHERE id='.(int)$messageId); }
    if ($fieldFixtureId) { $db->query('DELETE FROM f_module_field WHERE id='.(int)$fieldFixtureId); }
    $cleanupExtra=$db->prepare('DELETE FROM f_base WHERE copyright=?');
    $cleanupExtra->bind_param('s',$extraBaseCopyright); $cleanupExtra->execute(); $cleanupExtra->close();
    $restore=$db->prepare('UPDATE f_base SET name=?,copyright=?'); $restore->bind_param('ss',$originalName,$originalBase['copyright']); $restore->execute();
    if ($originalSettings===null) { if (is_file($settingsPath)) { unlink($settingsPath); } }
    else { file_put_contents($settingsPath,$originalSettings); }
    foreach ([$user,$legacyUser,$editorUser,'absent_'.$tag,'admin" OR 1=1 #'] as $name) {
        $bucket=hash_hmac('sha256','user:'.mb_strtolower($name,'UTF-8'),getenv('APP_KEY'));
        $stmt=$db->prepare('DELETE FROM f_login_attempts WHERE bucket=?'); $stmt->bind_param('s',$bucket); $stmt->execute();
    }
    $bucket=hash_hmac('sha256','ip:'.gethostbyname('web'),getenv('APP_KEY'));
    $stmt=$db->prepare('DELETE FROM f_login_attempts WHERE bucket=?'); $stmt->bind_param('s',$bucket); $stmt->execute();
    $bucket=hash_hmac('sha256','captcha-ip:'.gethostbyname('web'),getenv('APP_KEY'));
    $stmt=$db->prepare('DELETE FROM f_login_attempts WHERE bucket=?'); $stmt->bind_param('s',$bucket); $stmt->execute();
    $db->query("DROP TABLE IF EXISTS `$fixture`");
}
