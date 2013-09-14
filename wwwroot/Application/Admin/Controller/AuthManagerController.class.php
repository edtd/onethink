<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2013 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 朱亚杰 <zhuyajie@topthink.net>
// +----------------------------------------------------------------------
namespace Admin\Controller;
use Admin\Model\AuthRuleModel;
use Admin\Model\AuthGroupModel;
/**
 * 权限管理控制器
 * Class AuthManagerController
 * @author 朱亚杰 <zhuyajie@topthink.net>
 */
class AuthManagerController extends AdminController{

    /* 因为updateRules要供缓存管理模块内部使用,无需通过url访问;
     * 而delete,forbid,resume 已经通过changeStatus访问内部调用了,所以也不允许url访问 */
    static protected $deny  = array('updateRules','tree');

    /* 保存允许所有管理员访问的公共方法 */
    static protected $allow = array();

    static protected $nodes= array(
        //权限管理页
        array('title'=>'权限管理','url'=>'AuthManager/index','group'=>'用户管理',
              'operator'=>array(
                  //权限管理页面的五种按钮
                  array('title'=>'删除',        'url'=>'AuthManager/changeStatus?method=deleteGroup','tip'=>'删除用户组'),
                  array('title'=>'禁用',        'url'=>'AuthManager/changeStatus?method=forbidGroup','tip'=>'禁用用户组'),
                  array('title'=>'恢复',        'url'=>'AuthManager/changeStatus?method=resumeGroup','tip'=>'恢复已禁用的用户组'),
                  array('title'=>'新增',        'url'=>'AuthManager/createGroup',                    'tip'=>'创建新的用户组'),
                  array('title'=>'编辑',        'url'=>'AuthManager/editGroup',                      'tip'=>'编辑用户组名称和描述'),
                  array('title'=>'保存用户组',  'url'=>'AuthManager/writeGroup',                     'tip'=>'新增和编辑用户组的"保存"按钮'),
                  array('title'=>'授权',        'url'=>'AuthManager/group',                          'tip'=>'"后台 \ 用户 \ 用户信息"列表页的"授权"操作按钮,用于设置用户所属用户组'),
                  array('title'=>'访问授权',    'url'=>'AuthManager/access',                         'tip'=>'"后台 \ 用户 \ 权限管理"列表页的"访问授权"操作按钮'),
                  array('title'=>'成员授权',    'url'=>'AuthManager/user',                           'tip'=>'"后台 \ 用户 \ 权限管理"列表页的"成员授权"操作按钮'),
                  array('title'=>'解除授权',    'url'=>'AuthManager/removeFromGroup',                'tip'=>'"成员授权"列表页内的解除授权操作按钮'),
                  array('title'=>'保存成员授权','url'=>'AuthManager/addToGroup',                     'tip'=>'"用户信息"列表页"授权"时的"保存"按钮和"成员授权"里右上角的"添加"按钮)'),
                  array('title'=>'分类授权',    'url'=>'AuthManager/category',                       'tip'=>'"后台 \ 用户 \ 权限管理"列表页的"分类授权"操作按钮'),
                  array('title'=>'保存分类授权','url'=>'AuthManager/addToCategory',                  'tip'=>'"分类授权"页面的"保存"按钮'),
              ),
        ),
    );

    /**
     * 后台节点配置的url作为规则存入auth_rule
     * 执行新节点的插入,已有节点的更新,无效规则的删除三项任务
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function updateRules()
    {
        //需要新增的节点必然位于$nodes
        $nodes    = $this->returnNodes(false);

        $AuthRule = D('AuthRule');
        $map      = array('module'=>'admin','type'=>array('in','1,2'));//status全部取出,以进行更新
        //需要更新和删除的节点必然位于$rules
        $rules    = $AuthRule->where($map)->order('name')->select();

        //构建insert数据
        $data     = array();//保存需要插入和更新的新节点
        foreach ($nodes as $value){
            $temp['name']   = $value['url'];
            $temp['title']  = $value['title'];
            $temp['module'] = 'admin';
            if(isset($value['controllers'])){
                $temp['type'] = AuthRuleModel::RULE_MAIN;
            }else{
                $temp['type'] = AuthRuleModel::RULE_URL;
            }
            $temp['status']   = 1;
            $data[strtolower($temp['name'].$temp['module'].$temp['type'])] = $temp;//去除重复项
        }

        $update = array();//保存需要更新的节点
        $ids    = array();//保存需要删除的节点的id
        foreach ($rules as $index=>$rule){
            $key = strtolower($rule['name'].$rule['module'].$rule['type']);
            if ( isset($data[$key]) ) {//如果数据库中的规则与配置的节点匹配,说明是需要更新的节点
                $data[$key]['id'] = $rule['id'];//为需要更新的节点补充id值
                $update[] = $data[$key];
                unset($data[$key]);
                unset($rules[$index]);
                unset($rule['condition']);
                $diff[$rule['id']]=$rule;
            }elseif($rule['status']==1){
                $ids[] = $rule['id'];
            }
        }
        if ( count($update) ) {
            foreach ($update as $k=>$row){
                if ( $row!=$diff[$row['id']] ) {
                    $AuthRule->where(array('id'=>$row['id']))->save($row);
                }
            }
        }
        if ( count($ids) ) {
            $AuthRule->where( array( 'id'=>array('IN',implode(',',$ids)) ) )->save(array('status'=>-1));
            //删除规则是否需要从每个用户组的访问授权表中移除该规则?
        }
        if( count($data) ){
            $AuthRule->addAll(array_values($data));
        }
        if ( $AuthRule->getDbError() ) {
            trace('['.__METHOD__.']:'.$AuthRule->getDbError());
            return false;
        }else{
            return true;
        }
    }
    

    /**
     * 权限管理首页
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function index()
    {
        $list = $this->lists('AuthGroup',array('module'=>'admin'),'id asc');
        $list = intToString($list);
        $this->assign( '_list', $list );
        $this->assign( '_use_tip', true );
        cookie( 'auth_index',__SELF__);
		$this->meta_title = '权限管理';
        $this->display();
    }

    /**
     * 创建管理员用户组
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function createGroup()
    {
        if ( empty($this->auth_group) ) {
            $this->assign('auth_group',array('title'=>null,'id'=>null,'description'=>null,'rules'=>null,));//排除notice信息
        }
        $this->display('editgroup');
    }

    /**
     * 编辑管理员用户组
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function editGroup()
    {
        $auth_group = D('AuthGroup')->where( array('module'=>'admin','type'=>AuthGroupModel::TYPE_ADMIN) )
                                    ->find( (int)$_GET['id'] );
        $this->assign('auth_group',$auth_group);
        $this->display();
    }
    

    /**
     * 访问授权页面
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function access()
    {
        $this->updateRules();
        $auth_group = D('AuthGroup')->where( array('status'=>array('egt','0'),'module'=>'admin','type'=>AuthGroupModel::TYPE_ADMIN) )
									->getfield('id,id,title,rules');
        $node_list   = $this->returnNodes();
        $map         = array('module'=>'admin','type'=>AuthRuleModel::RULE_MAIN,'status'=>1);
        $main_rules  = D('AuthRule')->where($map)->getField('name,id');
        $map         = array('module'=>'admin','type'=>AuthRuleModel::RULE_URL,'status'=>1);
        $child_rules = D('AuthRule')->where($map)->getField('name,id');

        $this->assign('main_rules',$main_rules);
        $this->assign('auth_rules',$child_rules);
        $this->assign('node_list',$node_list);
        $this->assign('auth_group',$auth_group);
		$this->assign('this_group',$auth_group[(int)$_GET['group_id']]);
		$this->meta_title = '权限管理-访问授权';
        $this->display('managergroup');
    }
    
    /**
     * 管理员用户组数据写入/更新
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function writeGroup()
    {
        if(isset($_POST['rules'])){
            sort($_POST['rules']);
            $_POST['rules']  = trim( implode( ',' , array_unique($_POST['rules'])) , ',' );
        }
        $_POST['module'] = 'admin';
        $_POST['type']   = AuthGroupModel::TYPE_ADMIN;
        $AuthGroup       = D('AuthGroup');
        $data = $AuthGroup->create();
        if ( $data ) {
            if ( empty($data['id']) ) {
                $r = $AuthGroup->add();
            }else{
                $r = $AuthGroup->save();
            }
            if($r===false){
                $this->error('操作失败'.$AuthGroup->getError());
            } else{
                $this->success('操作成功!');
            }
        }else{
            $this->error('操作失败'.$AuthGroup->getError());
        }
    }
    
    /**
     * 状态修改
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function changeStatus($method=null)
    {
        if ( empty($_GET['id']) ) {
            $this->error('请选择要操作的数据!');
        }
        switch ( strtolower($method) ){
            case 'forbidgroup':
                $this->forbid('AuthGroup');
                break;
            case 'resumegroup':
                $this->resume('AuthGroup');
                break;
            case 'deletegroup':
                $this->delete('AuthGroup');
                break;
            default:
                $this->error($method.'参数非法');
        }
    }

    /**
     * 用户组授权用户列表
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function user($group_id){
        if(empty($group_id)){
            $this->error('参数错误');
        }

		$auth_group = D('AuthGroup')->where( array('status'=>array('egt','0'),'module'=>'admin','type'=>AuthGroupModel::TYPE_ADMIN) )
			->getfield('id,id,title,rules');
        $prefix   = C('DB_PREFIX');
        $l_table  = $prefix.(AuthGroupModel::MEMBER);
        $r_table  = $prefix.(AuthGroupModel::AUTH_GROUP_ACCESS);
        $list     = M() ->field('m.uid,m.nickname,m.last_login_time,m.last_login_ip,m.status')
                       ->table($l_table.' m')
                       ->join($r_table.' a ON m.uid=a.uid');
        $_REQUEST = array();
        $list = $this->lists($list,array('a.group_id'=>$group_id,'m.status'=>array('egt',0)),'m.uid asc',array());
        intToString($list);
        $this->assign( '_list', $list );
		$this->assign('auth_group',$auth_group);
		$this->assign('this_group',$auth_group[(int)$_GET['group_id']]);
		$this->meta_title = '权限管理-成员授权';
        $this->display();
    }

    /**
     * 将分类添加到用户组的编辑页面
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function category(){
		$auth_group = D('AuthGroup')->where( array('status'=>array('egt','0'),'module'=>'admin','type'=>AuthGroupModel::TYPE_ADMIN) )
			->getfield('id,id,title,rules');
        $group_list   = D('Category')->getTree();
        $authed_group = AuthGroupModel::getCategoryOfGroup(I('group_id'));
        $this->assign('authed_group',implode(',',(array)$authed_group));
        $this->assign('group_list',$group_list);
		$this->assign('auth_group',$auth_group);
		$this->assign('this_group',$auth_group[(int)$_GET['group_id']]);
		$this->meta_title = '权限管理-分类授权';
        $this->display();
    }

    public function tree($tree = null){
        $this->assign('tree', $tree);
        $this->display('tree');
    }

    /**
     * 将用户添加到用户组的编辑页面
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function group()
    {
        $uid = I('uid');
        $auth_groups = D('AuthGroup')->getGroups();
        $user_groups = AuthGroupModel::getUserGroup($uid);
        $ids = array();
        foreach ($user_groups as $value){
            $ids[] = $value['group_id'];
        }
        $nickname = D('Member')->getNickName($uid);
        $this->assign('nickname',$nickname);
        $this->assign('auth_groups',$auth_groups);
        $this->assign('user_groups',implode(',',$ids));
        $this->display();
    }
    
    /**
     * 将用户添加到用户组,入参uid,group_id
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function addToGroup()
    {
        $uid = I('uid');
        $gid = I('group_id');
        if( empty($uid) ){
            $this->error('参数有误');
        }
        $AuthGroup = D('AuthGroup');
		if(is_numeric($uid)){
			if ( C('USER_ADMINISTRATOR')==$uid ) {
				$this->error('该用户为超级管理员');
			}
			if( !M('Member')->where(array('uid'=>$uid))->find() ){
				$this->error('管理员用户不存在');
			}
		}

        if( $gid && !$AuthGroup->checkGroupId($gid)){
            $this->error($AuthGroup->error);
        }
        if ( $AuthGroup->addToGroup($uid,$gid) ){
            $this->success('操作成功');
        }else{
            $this->error($AuthGroup->getError());
        }
    }

    /**
     * 将用户从用户组中移除  入参:uid,group_id
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function removeFromGroup()
    {
        $uid = I('uid');
        $gid = I('group_id');
        if( $uid==$this->getVal('uid') ){
            $this->error('不允许解除自身授权');
        }
        if( empty($uid) || empty($gid) ){
            $this->error('参数有误');
        }
        $AuthGroup = D('AuthGroup');
        if( !$AuthGroup->find($gid)){
            $this->error('用户组不存在');
        }
        if ( $AuthGroup->removeFromGroup($uid,$gid) ){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }

    /**
     * 将分类添加到用户组  入参:cid,group_id
     * @author 朱亚杰 <zhuyajie@topthink.net>
     */
    public function addToCategory()
    {
        $cid = I('cid');
        $gid = I('group_id');
        if( empty($gid) ){
            $this->error('参数有误');
        }
        $AuthGroup = D('AuthGroup');
        if( !$AuthGroup->find($gid)){
            $this->error('用户组不存在');
        }
        if( $cid && !$AuthGroup->checkCategoryId($cid)){
            $this->error($AuthGroup->error);
        }
        if ( $AuthGroup->addToCategory($gid,$cid) ){
            $this->success('操作成功');
        }else{
            $this->error('操作失败');
        }
    }
    
}
