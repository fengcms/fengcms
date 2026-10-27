<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/tools/migrate.php';
$db=migration_connection();
if (!$db->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) {
    exit("Refusing mutation tests outside the disposable development database.\n");
}
define('TPL_INCLUDE',true);
define('URL_TYPE',0);
require dirname(__DIR__).'/system/common/security.php';
require dirname(__DIR__).'/system/common/function.php';

function compatibility_page(string $path): array
{
    if (str_starts_with($path,'?')) { $path='/'.$path; }
    $endpoint=getenv('FENGCMS_COMPAT_HTTP') ?: 'web:8080';
    $socket=stream_socket_client('tcp://'.$endpoint,$errorNumber,$errorMessage,10);
    if (!$socket) { throw new RuntimeException($errorMessage); }
    stream_set_timeout($socket,30);
    $request="GET {$path} HTTP/1.0\r\nHost: localhost\r\nConnection: close\r\n\r\n";
    for ($offset=0;$offset<strlen($request);) {
        $written=fwrite($socket,substr($request,$offset));
        if (!$written) { throw new RuntimeException('HTTP write failed.'); }
        $offset+=$written;
    }
    $response=stream_get_contents($socket);
    $timedOut=stream_get_meta_data($socket)['timed_out'];
    fclose($socket);
    if ($timedOut || !str_contains($response,"\r\n\r\n")) { throw new RuntimeException('HTTP read failed.'); }
    [$headers,$body]=explode("\r\n\r\n",$response,2);
    preg_match('~HTTP/\S+ ([0-9]{3})~',$headers,$status);
    return [(int)($status[1]??0),$body];
}
function compatibility_expect(bool $okay,string $name): void
{
    if (!$okay) { throw new RuntimeException('FAIL '.$name); }
    echo "PASS $name\n";
}
function compatibility_hits(mysqli $db,int $id): int
{
    return (int)$db->query('SELECT hits FROM f_article WHERE id='.$id)->fetch_row()[0];
}

$prefix='qa_compat_'.bin2hex(random_bytes(5));
$project='QaMix_'.bin2hex(random_bytes(4));
$table='f_'.$project;
$categoryIds=[]; $moduleId=null; $tableCreated=false;
try {
    $db->query('CREATE TABLE `'.$table.'` (
        id int NOT NULL AUTO_INCREMENT PRIMARY KEY, title varchar(255), classid varchar(255),
        info text, content longtext, img text, date date, time int, status int DEFAULT 1,
        html varchar(255), template varchar(255), origin varchar(255), author varchar(255),
        inputer varchar(255), tags varchar(255), hits int DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $tableCreated=true;
    $module=$db->prepare('INSERT INTO f_module (name,project,type,search,status) VALUES (?,?,1,0,1)');
    $moduleName=$prefix;
    $module->bind_param('ss',$moduleName,$project);$module->execute();$moduleId=$db->insert_id;$module->close();

    $categories=[];
    $insert=$db->prepare("INSERT INTO f_classify (name,type,project,enname,classid,nav,classify_template,channel_template,content_template)
        VALUES (?,'module',?,?,'0',0,'/article_class.html','/article_class.html','/article_content.html')");
    foreach ([['article','news'],['article','news_local'],[$project,'news'],[$project,'news_local']] as $index=>[$model,$slug]) {
        $name=$prefix.'_category_'.$index;
        $insert->bind_param('sss',$name,$model,$slug);$insert->execute();
        $id=$db->insert_id;$categoryIds[]=$id;
        $path='/'.$model.'_'.$slug.'_'.$id.'.html';
        $db->query("UPDATE f_classify SET html='".$db->real_escape_string($path)."' WHERE id=$id");
        $categories[]=['model'=>$model,'slug'=>$slug,'id'=>$id,'name'=>$name,'path'=>$path];
    }
    $insert->close();

    foreach ($categories as $category) {
        [$status,$body]=compatibility_page('/?controller=classify&project='.rawurlencode($category['model']).
            '&classify='.rawurlencode($category['slug']).'&classid='.$category['id']);
        compatibility_expect($status===200 && str_contains($body,$category['name']),
            'explicit category route: '.$category['model'].' / '.$category['slug']);
        foreach ([url($category['path']),$category['path']] as $path) {
            [$status,$body]=compatibility_page($path);
            compatibility_expect($status===200 && str_contains($body,$category['name']),
                'category route '.$path.' returns its own record (HTTP '.$status.')');
        }
    }
    compatibility_expect(str_starts_with(url($categories[1]['path']),'/?controller=classify&route='),
        'generated category link is absolute from a rewritten page');
    compatibility_expect(compatibility_page('/article_news&controller=home_'.$categoryIds[0].'.html')[0]===404,
        'category path cannot inject a controller parameter');
    [$overrideStatus,$overrideBody]=compatibility_page($categories[0]['path'].'?controller=home');
    compatibility_expect($overrideStatus===200 && str_contains($overrideBody,$categories[0]['name']),
        'category path ignores a conflicting query controller');

    $customTitle=$prefix.'_mixed_content';
    $db->query("INSERT INTO `{$table}` (title,classid,content,template,status,date,time) VALUES ('{$customTitle}',".
        $categoryIds[3].",'test','/single.html',1,CURDATE(),UNIX_TIMESTAMP())");
    $customId=$db->insert_id;
    $customPath='/content_'.$project.'_'.$customId.'.html';
    foreach ([url($customPath),$customPath] as $path) {
        [$status,$body]=compatibility_page($path);
        compatibility_expect($status===200 && str_contains($body,$customTitle),
            'mixed-case underscored model content link renders');
    }
    compatibility_expect(compatibility_page('/?controller=content&project='.strtolower($project).'&id='.$customId)[0]===404,
        'mixed-case registered project requires its exact spelling');
    $db->query('UPDATE f_module SET status=0 WHERE id='.$moduleId);
    compatibility_expect(compatibility_page($customPath)[0]===404,'disabled content model stays private');
    $db->query('UPDATE f_module SET status=1 WHERE id='.$moduleId);

    $articleInsert=$db->prepare("INSERT INTO f_article (title,classid,content,template,status,date,time)
        VALUES (?,?,'test','/article_content.html',1,CURDATE(),UNIX_TIMESTAMP())");
    foreach ([0,2147000000,$categoryIds[1]] as $classid) {
        $title=$prefix.'_content_'.$classid;
        $articleInsert->bind_param('si',$title,$classid);$articleInsert->execute();$id=$db->insert_id;
        $before=compatibility_hits($db,$id);
        [$status,$body]=compatibility_page('/?controller=content&project=article&id='.$id);
        compatibility_expect($status===200 && str_contains($body,$title) && compatibility_hits($db,$id)===$before+1,
            'content with valid, zero or orphan category renders once');
        if ($classid===$categoryIds[1]) {
            compatibility_expect(str_contains($body,$categories[1]['name']),'valid category name remains visible');
            foreach (['ARTICLE','Article'] as $variant) {
                compatibility_expect(compatibility_page('/?controller=content&project='.$variant.'&id='.$id)[0]===404 &&
                    compatibility_hits($db,$id)===$before+1,'case variant cannot reach a different content table');
            }
        }
    }
    $articleInsert->close();

    $keywords=['普通中文','C++','A&B','a/b','x=1','what?','literal%2F','part_2','part_2026','part_0','many_under_scores_2'];
    foreach ($keywords as $keyword) {
        $title=$prefix.'_keyword_'.$keyword;
        $escaped=$db->real_escape_string($title);
        $db->query("INSERT INTO f_article (title,status,time,date) VALUES ('{$escaped}',1,UNIX_TIMESTAMP(),CURDATE())");
        $extra=[];
        for ($i=1;$i<=100;$i++) {
            $extraTitle=$db->real_escape_string($title.'_'.$i);
            $extra[]="('{$extraTitle}',1,UNIX_TIMESTAMP(),CURDATE())";
        }
        $db->query('INSERT INTO f_article (title,status,time,date) VALUES '.implode(',',$extra));
        [$status,$body]=compatibility_page(search($keyword));
        $visible=htmlspecialchars($keyword,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        compatibility_expect($status===200 && str_contains($body,'搜索“'.$visible.'”') &&
            str_contains($body,htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')),
            'search helper preserves keyword: '.$keyword);
        compatibility_expect(preg_match('~href="([^"]*page=2[^"]*)"~',$body,$next)===1,
            'search helper emits a second-page link: '.$keyword);
        $nextPath=html_entity_decode($next[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
        [$nextStatus,$nextBody]=compatibility_page($nextPath);
        compatibility_expect($nextStatus===200 && str_contains($nextBody,'搜索“'.$visible.'”') &&
            str_contains($nextBody,htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')),
            'search second page preserves keyword: '.$keyword.' URL '.$nextPath.' HTTP '.$nextStatus.
            ' keyword='.((int)str_contains($nextBody,'搜索“'.$visible.'”')).
            ' record='.((int)str_contains($nextBody,htmlspecialchars($title,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'))));
        if (in_array($keyword,['普通中文','C++','literal%2F'],true)) {
            [$status,$body]=compatibility_page('/tags/'.rawurlencode($keyword).'.html');
            compatibility_expect($status===200 && str_contains($body,'搜索“'.$visible.'”'),
                'safe rewritten keyword still round-trips');
        }
    }
    compatibility_expect(compatibility_page('/tags/x&controller=home.html')[0]===404,
        'tag path cannot inject a controller parameter');
    compatibility_expect(compatibility_page('/tags/x%26controller%3Dhome.html')[0]===404,
        'encoded tag path cannot inject a controller parameter');
    [$cloudStatus,$cloudBody]=compatibility_page('/tags.html?controller=home');
    compatibility_expect($cloudStatus===200 && str_contains($cloudBody,'id="tags_list"'),
        'tag cloud route ignores a conflicting query controller');

    $boundary=$prefix.'_boundary';
    $values=[];
    for ($i=0;$i<=1000;$i++) {
        $title=$db->real_escape_string($boundary.'_'.$i);
        $values[]="('{$title}',{$categoryIds[1]},1,UNIX_TIMESTAMP(),CURDATE())";
    }
    $db->query('INSERT INTO f_article (title,classid,status,time,date) VALUES '.implode(',',$values));
    $categoryBase=$categories[1]['path'];
    $categorySecond=substr($categoryBase,0,-5).'_2.html';
    [$status,$body]=compatibility_page($categorySecond);
    compatibility_expect($status===200 && str_contains($body,$boundary.'_900') &&
        !str_contains($body,$boundary.'_1000'),'underscored category page two reads the correct records');
    [$status,$body]=compatibility_page(url($categoryBase));
    compatibility_expect($status===200 && preg_match('~href="([^"]*page=2[^"]*)"~',$body,$categoryNext)===1 &&
        !str_contains($body,'route=article_news_local_'.$categoryIds[1].'_2'),
        'category pager generates an unambiguous dynamic page link');
    [$status,$body]=compatibility_page(html_entity_decode($categoryNext[1],ENT_QUOTES|ENT_HTML5,'UTF-8'));
    compatibility_expect($status===200 && str_contains($body,$boundary.'_900') &&
        !str_contains($body,$boundary.'_1000'),'generated category page-two link is usable');
    foreach ([1,2,100] as $page) {
        [$status,$body]=compatibility_page('/?controller=search&project=article&tags='.$boundary.'&page='.$page);
        compatibility_expect($status===200 && str_contains($body,'1001条') &&
            str_contains($body,'最多可浏览前 100 页') && !str_contains($body,'page=101'),
            'bounded search page '.$page.' has no unreachable link');
    }
    [$status,$firstSearch]=compatibility_page('/?controller=search&project=article&tags='.$boundary);
    compatibility_expect($status===200 && preg_match('~href="([^"]*page=100[^"]*)"~',$firstSearch,$last)===1,
        'bounded search offers a last-page link');
    [$status,$lastSearch]=compatibility_page(html_entity_decode($last[1],ENT_QUOTES|ENT_HTML5,'UTF-8'));
    compatibility_expect($status===200 && str_contains($lastSearch,'搜索“'.$boundary.'”') &&
        !str_contains($lastSearch,'page=101'),'generated search last-page link is usable');
    compatibility_expect(compatibility_page('/?controller=search&project=article&tags='.$boundary.'&page=0')[0]===400 &&
        compatibility_page('/?controller=search&project=article&tags='.$boundary.'&page=101')[0]===400,
        'search rejects pages outside the documented browsing range');
} finally {
    $cleanup=$db->prepare('DELETE FROM f_article WHERE LEFT(title,?)=?');
    $length=strlen($prefix);$cleanup->bind_param('is',$length,$prefix);$cleanup->execute();$cleanup->close();
    foreach ($categoryIds as $id) { $db->query('DELETE FROM f_classify WHERE id='.(int)$id); }
    if ($moduleId) { $db->query('DELETE FROM f_module WHERE id='.(int)$moduleId); }
    if ($tableCreated) { $db->query('DROP TABLE `'.$table.'`'); }
}
