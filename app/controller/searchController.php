<?php
class searchController extends Controller
{
    public function index()
    {
        $input = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;
        $tags = $input['tags'] ?? '';
        $project = $input['project'] ?? '';
        if (!is_string($tags) || !is_string($project) || mb_strlen($tags, 'UTF-8') > 80 ||
            ($project !== '' && !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $project))) {
            cms_fail(400, '搜索参数不正确。');
        }
        $page=$_GET['page'] ?? '1';
        if (!is_scalar($page) || !preg_match('/^[1-9][0-9]{0,2}$/D', (string)$page) || (int)$page>100) {
            cms_fail(400, '搜索页码不正确。');
        }
        if ($project !== '' && !D('module')->where(['project'=>$project,'type'=>1,'search'=>1,'status'=>1])->getone()) {
            cms_fail(404, '搜索模型不存在。');
        }
        if ($tags === '') { cms_fail(400, '请输入关键词。'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $query = ['controller'=>'search','tags'=>$tags];
            if ($project !== '') { $query['project']=$project; }
            $url = '/?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            header('Location: '.$url, true, 303);
            return;
        }
        $now=time();
        $window=$_SESSION['search_window'] ?? null;
        if (!is_array($window) || !isset($window['start'],$window['count']) || $now-(int)$window['start']>=60) {
            $window=['start'=>$now,'count'=>0];
        }
        if (++$window['count']>60) {
            header('Retry-After: 60');
            cms_fail(429, '搜索请求过于频繁，请稍后重试。');
        }
        $_SESSION['search_window']=$window;
        return $this->display($project !== '' ? $project.'_search.html' : 'search.html');
    }
}
