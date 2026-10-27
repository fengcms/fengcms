<?php
require_once dirname(__DIR__).'/searchCriteria.php';

class searchController extends Controller
{
    public function index()
    {
        $input = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;
        try {
            $criteria = CmsSearchCriteria::fromInput($input, $_GET['page'] ?? '1');
        } catch (InvalidArgumentException $error) {
            cms_fail(400, $error->getMessage());
        }
        if ($criteria->project !== '' && !D('module')->where(['project'=>$criteria->project,'type'=>1,'search'=>1,'status'=>1])->getone()) {
            cms_fail(404, '搜索模型不存在。');
        }
        if ($criteria->tags === '') { cms_fail(400, '请输入关键词。'); }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $query = ['controller'=>'search','tags'=>$criteria->tags];
            if ($criteria->project !== '') { $query['project']=$criteria->project; }
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
        return $this->display($criteria->project !== '' ? $criteria->project.'_search.html' : 'search.html');
    }
}
