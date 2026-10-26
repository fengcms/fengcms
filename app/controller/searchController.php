<?php
class searchController extends Controller
{
    public function index()
    {
        $input = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? $_POST : $_GET;
        $tags = $input['tags'] ?? '';
        $project = $input['project'] ?? '';
        if (!is_string($tags) || !is_string($project) || mb_strlen($tags, 'UTF-8') > 255 ||
            ($project !== '' && !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $project))) {
            cms_fail(400, '搜索参数不正确。');
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
        return $this->display($project !== '' ? $project.'_search.html' : 'search.html');
    }
}
