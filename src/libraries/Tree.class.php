<?php

/**
 * 通用的树型类，可以生成任何树型结构
 */
class Tree {

    /**
     * 分类的父ID的键名(key)
     *
     * @var integer
     */
    private $parentid;

    /**
     * 分类的ID(key)
     *
     * @var integer
     */
    private $id;

    /**
     * 分类名
     *
     * @var string
     */
    private $name;

    /**
     * 数据
     *
     * @var array
     */
    private $data;

    /**
     * 无限级分类树-初始化配置
     *
     * @param  array $$params 配置分类的键
     * @return $this
     *
     * @example
     * $params = ['parent_id'=>'pid', 'id' => 'cat_id', 'name' =>'cat_name'];
     * $this->config($params );
     */
    public function config($params) {
        if (!$params || !is_array($params)) {
            return false;
        }
        $this->parentid = (isset($params['parent_id'])) ? $params['parent_id'] : $this->parentid;
        $this->id = (isset($params['id'])) ? $params['id'] : $this->id;
        $this->name = (isset($params['name'])) ? $params['name'] : $this->name;
        return $this;
    }

    /**
     * 无限级分类树-获取树
     *
     * @param  array 	$data 			树的数组
     * @param  int   	$parent_id 		初始化树时候，代表ID下的所有子集
     * @param  int   	$select_id              选中的ID值
     * @param  string  	$pre_fix  		前缀
     * @param  string  	$child  		是否禁用父栏目
     * @return string|array
     */
    public function get_tree($data, $parent_id = 0, $select_id = null, $pre_fix = '|- ', $child = false) {
        //parse params
        if (!$data || !is_array($data)) {
            return '';
        }
        $string = '';
        foreach ($data as $key => $value) {
            if ($child && ($value['child'] == 0)) {
                continue;
            }
            if ($value[$this->parentid] == $parent_id) {
                $string .= '<option value=\'' . $value[$this->id] . '\'';
                if (!is_null($select_id)) {
                    $string .= ($value[$this->id] == $select_id) ? ' selected="selected"' : '';
                }
                if ($child && $value['child'] == 1) {
                    $string .= ' disabled';
                }
                $string .= '>' . ($value[$this->parentid] == 0 ? '' : $pre_fix) . $value[$this->name] . '</option>';

                $string .= $this->get_tree($data, $value[$this->id], $select_id, '&nbsp;&nbsp;' . $pre_fix, $child);
            }
        }
        return $string;
    }

    /**
     * 无限级分类树-获取数据
     *
     * @param  array 	$data 			树的数组
     * @param  int   	$parent_id 		初始化树时候，代表ID下的所有子集
     * @param  string  	$pre_fix  		前缀
     * @return string|array
     */
    public function get_tree_data($data, $parent_id = 0, $pre_fix = '|-') {
        if (!$data || !is_array($data)) {
            return '';
        }
        foreach ($data as $key => $value) {
            if ($value[$this->parentid] == $parent_id) {
                $this->data[$key] = $value;
                if ($parent_id == 0) {
                    $this->data[$key]['prefix'] = $value[$this->name];
                } else {
                    $this->data[$key]['prefix'] = $pre_fix . '&nbsp;' . $value[$this->name];
                }
                $this->get_tree_data($data, $value[$this->id], '&nbsp;&nbsp;&nbsp;&nbsp;' . $pre_fix);
            }
        }
        return $this->data;
    }

    /**
     * 新增的无限分类
     */
    private $result;
    private $tmp;
    private $arr;
    private $already = [];

    /**
     * 构造函数
     *
     * @param array $result 树型数据表结果集
     * @param integer $root 顶级分类的父id
     */
    public function __construct($result = [], $root = 0) {
        $this->parentid = 'parent_id';
        $this->id = 'id';
        $this->name = 'name';
        $this->result = $result;
        $this->root = $root;
        if (!empty($this->result)) {
            $this->init();
        }
    }

    /**
     * 树型数据表结果集处理
     */
    private function init() {
        foreach ($this->result as $node) {
            $tmp[$node[$this->parentid]][] = $node;
        }
        if (is_array($tmp)) {
            krsort($tmp);
        }
        for ($i = count($tmp); $i > 0; $i--) {
            foreach ($tmp as $k => $v) {
                if (!in_array($k, $this->already)) {
                    if (!$this->tmp) {
                        $this->tmp = array($k, $v);
                        $this->already[] = $k;
                        continue;
                    } else {
                        foreach ($v as $key => $value) {
                            if ($value[$this->id] == $this->tmp[0]) {
                                $tmp[$k][$key]['child'] = $this->tmp[1];
                                $this->tmp = array($k, $tmp[$k]);
                            }
                        }
                    }
                }
            }
            $this->tmp = null;
        }
        $this->tmp = $tmp;
    }

    /**
     * 反向递归
     */
    private function recur_n($arr, $id) {
        if (is_array($arr)) {
            foreach ($arr as $v) {
                if ($v[$this->id] == $id) {
                    $this->arr[] = $v;
                    if ($v[$this->parentid] != $this->root) {
                        $this->recur_n($arr, $v[$this->parentid]);
                    }
                }
            }
        }
    }

    /**
     * 正向递归
     */
    private function recur_p($arr) {
        if (is_array($arr)) {
            foreach ($arr as $v) {
                $this->arr[] = $v[$this->id];
                if ($v['child']) {
                    $this->recur_p($v['child']);
                }
            }
        }
    }

    /**
     * 菜单 多维数组
     *
     * @param integer $id 分类id
     * @return array 返回分支，默认返回整个树
     */
    public function leaf($id = null) {
        $id = ($id == null) ? $this->root : $id;
        return isset($this->tmp[$id]) ? $this->tmp[$id] : null;
    }

    /**
     * 导航 一维数组
     *
     * @param integer $id 分类id
     * @return array 返回单线分类直到顶级分类
     */
    public function navi($id = 0) {
        $this->arr = null;
        $this->recur_n($this->result, $id);
        if (is_array($this->arr)) {
            krsort($this->arr);
        }
        return $this->arr;
    }

    /**
     * 获取指定分类的子分类ids
     * @return Array
     * @param int $id
     * @param $exclude 是否排除分类本身
     */
    public function get_child_ids($id = 0, $exclude = true) {
        $this->arr = null;
        $this->arr[] = $id;
        $this->recur_p($this->leaf($id));
        $ids = [];
        foreach ($this->arr as $v) {
            if ($exclude) {
                if ($id <> $v) {
                    $ids[] = $v;
                }
            } else {
                $ids[] = $v;
            }
        }
        return $ids;
    }

    /**
     * 获取指定分类的上级分类IDs
     * return array
     * @param unknown_type $id
     * @param unknown_type $exclude 是否排除分类本身
     */
    public function get_parent_ids($id = 0, $exclude = true) {
        $this->arr = null;
        $this->recur_n($this->result, $id);
        if (is_array($this->arr)) {
            krsort($this->arr);
        }
        $ids = [];
        if (empty($this->arr)) {
            return $ids;
        }
        foreach ($this->arr as $v) {
            if (isset($v[$this->id])) {
                if ($exclude) {
                    if ($id <> $v[$this->id]) {
                        $ids[] = $v[$this->id];
                    }
                } else {
                    $ids[] = $v[$this->id];
                }
            }
        }
        return $ids;
    }

    /**
     * 散落 一维数组
     *
     * @param integer $id 分类id
     * @return array 返回leaf下所有分类id
     */
    public function leafid($id = 0) {
        $this->arr = null;
        $this->arr[] = $id;
        $this->recur_p($this->leaf($id));
        return $this->arr;
    }

    /**
     * 把返回的数据集转换成Tree
     * @access public
     * @param array $list 要转换的数据集
     * @param string $pid parent标记字段
     * @param string $level level标记字段
     * @return array
     */
    public static function list_to_tree($list, $pk = 'id', $pid = 'pid', $child = '_child', $root = 0) {
        // 创建Tree
        $tree = [];
        if (is_array($list)) {
            // 创建基于主键的数组引用
            $refer = [];
            foreach ($list as $key => $data) {
                $refer[$data[$pk]] = & $list[$key];
            }
            foreach ($list as $key => $data) {
                // 判断是否存在parent
                $parentId = $data[$pid];
                if ($root == $parentId) {
                    $tree[] = & $list[$key];
                } else {
                    if (isset($refer[$parentId])) {
                        $parent = & $refer[$parentId];
                        $parent[$child][] = & $list[$key];
                    }
                }
            }
        }
        return $tree;
    }

}
