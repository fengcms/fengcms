<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 首页模型

class classifyModel extends model{

	private $d_name		 = "classify";
    /** @var array<string, array{revision:int, children:array, navigation:array}> */
    private static array $requestTrees = [];

	public function __construct(){
		unset($this->options);
		parent::__construct($this->d_name);
	}

	public function classify($classid=0,$type=""){
		if($type)$where=' and type="'.$type.'"';
		return D($this->d_name)->where('classid="'.$classid.'"'.$where)->getall();
	}

	public function classside($classid){
		$array=D($this->d_name)->where('id="'.$classid.'"')->getone();
		return D($this->d_name)->where('classid="'.$array['classid'].'"')->getall();
	}


	public function classname($classid){
		$array=D($this->d_name)->where('id="'.$classid.'"')->getone();
		return $array['name'];
	}

    /**
     * 查询下级栏目
     *
     * @return array
     */
	public function children($id){
		$root=(int)$id;
		$ids=[]; $seen=[];
		$this->collectChildren($root, $ids, $seen);
		return implode(',', array_map(static fn($child) => 'classid = "'.$child.'"', $ids)).',';
	}

    private function snapshot(): array {
        $key = hash('sha256', serialize([DB_HOST, DB_NAME, DB_PREFIX]));
        $revision = model::requestWriteRevision();
        if (!isset(self::$requestTrees[$key]) || self::$requestTrees[$key]['revision'] !== $revision) {
            $rows = D($this->d_name)->excsql('SELECT id,classid,name,html,nav,url_type,type,sort FROM '.
                model::identifier(DB_PREFIX.$this->d_name).' ORDER BY sort ASC');
            $children = []; $navigation = [];
            foreach ($rows as $row) {
                $parent = (int)$row['classid'];
                $children[$parent][] = $row;
                if ((int)$row['nav'] === 1) { $navigation[$parent][] = $row; }
            }
            // The tree/map path previously requested sort,id. Navigation's
            // existing SQL requested sort alone, so retain its row order.
            foreach ($children as &$group) {
                usort($group, static fn($a, $b) => [(int)$a['sort'], (int)$a['id']] <=>
                    [(int)$b['sort'], (int)$b['id']]);
            }
            unset($group);
            self::$requestTrees[$key] = [
                'revision' => $revision, 'children' => $children, 'navigation' => $navigation,
            ];
        }
        return self::$requestTrees[$key];
	}

    private function tree(): array { return $this->snapshot()['children']; }

    /** The stock navigation's existing l() calls use this request-local snapshot. */
    public function navigationRows(int $parent, array $fields, int $limit): array {
        if ($limit <= 0) { return []; }
        $rows = [];
        foreach ($this->snapshot()['navigation'][$parent] ?? [] as $row) {
            $selected = [];
            foreach ($fields as $field) { $selected[$field] = $row[$field]; }
            $rows[] = $selected;
            if (count($rows) >= $limit) { break; }
        }
        return $rows;
    }

	private function collectChildren(int $id, array &$ids, array &$seen): void {
		if (isset($seen[$id])) { return; }
		$seen[$id]=true;
		foreach ($this->tree()[$id] ?? [] as $child) {
			$childId=(int)$child['id'];
			if (isset($seen[$childId])) { continue; }
			$ids[]=$childId;
			$this->collectChildren($childId, $ids, $seen);
		}
		$ids[]=$id;
	}

    /**
     * 所在位置导航
     *
     * @return array
     */
	public function wz($id,$str){
		$array='';
		$arr=D($this->d_name)->field('id,classid,name,html')->where('id = "'.$id.'"')->getone();
		if (!$arr) { return ''; }

		$array.=' '.cms_escape($str).' <a href="'.cms_escape(cms_safe_url(url($arr['html']))).'">'.cms_escape($arr['name']).'</a>,';

		if($arr['classid']>0){

			$array.=$this->wz($arr['classid'],$str).',';
		}
		return $array;
	}

    /**
     * 网站标题
     *
     * @return array
     */
	public function title($id,$str){
		$array='';
		$arr=D($this->d_name)->field('id,classid,name,html')->where('id = "'.$id.'"')->getone();
		if (!$arr) { return ''; }

		$array.=$arr['name'].' '.$str.' ,';

		if($arr['classid']>0){

			$array.=$this->title($arr['classid'],$str).',';
		}
		return $array;
	}

    /**
     * 网站地图
     *
     * @return array
     */
	public function map($classid=0){
		$seen=[];
		return cms_html($this->renderMap((int)$classid, $seen));
	}

	private function renderMap(int $parent, array &$seen): string {
		$html='';
		foreach ($this->tree()[$parent] ?? [] as $row) {
			$id=(int)$row['id'];
			if (isset($seen[$id])) { continue; }
			$seen[$id]=true;
			$html.='<li><a href="'.cms_escape(cms_safe_url(url($row['html']))).'">'.cms_escape($row['name']).'</a>';
			$nested=$this->renderMap($id, $seen);
			$html.=$nested!=='' ? '<ul>'.$nested.'</ul></li>' : '</li>';
		}
		return $html;
	}
}







?>
