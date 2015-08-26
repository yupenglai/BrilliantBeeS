<?php
/**
 * Created by PhpStorm.
 * User: pineapplebin
 * Date: 15-8-12
 * Time: 下午6:27
 */
namespace Admin\Controller;
use Common\Controller\AdminBaseController;
use Think\Page;

class PlateManageController extends AdminBaseController {

    /**
     * 显示板块信息
     */
    public function index(){
        import('Think.Page');

        // 分页相关
        $count = M('plate')->count();
        $page = new Page($count,15);
        $limit = $page->firstRow.','.$page->listRows;

        // 查询板块数据
        $plate = array();
        $result = M('user_plate_relation')->join('RIGHT JOIN bbs_plate ON bbs_user_plate_relation.plate_id=bbs_plate.plate_id')->order('bbs_plate.plate_id DESC')->limit($page->firstRow.','.$page->listRows)->select();
        foreach ($result as $key=>$value) {
            $index = (int)$value['plate_id'];
            if ($value['user_id'] == null || empty($plate[$index])) {
                $plate[$index] = $value;
            } else {
                if (!is_array($plate[$index]['user_id'])) {
                    $plate[$index]['user_id'] = array($plate[$index]['user_id']);
                }
                $plate[$index]['user_id'][] = $value['user_id'];
            }
        }

        $this->assign('plate', $plate);
        $this->assign('page', $page->show());
        $this->display();
    }

    /**
     * 新建板块
     */
    public function newPlate(){
        $this->display();
    }

    /**
     * 新建板块处理
     */
    public function newPlateHandle(){
        if (!IS_POST) $this->redirect('index');

        // 判断是否为空
        // plateName 板块名称
        // plateDescription 板块描述
        if (I('post.plateName') == '' || I('post.plateDescription') == '') {
            flash('请输入板块完整信息');
            $this->redirect('newPlate');
        }

        // 名称查重
        $newPlate = M('plate');
        $is_exist = $newPlate->where(array('plate_name'=>I('post.plateName')))->find();
        if ($is_exist) {
            flash('板块名已被使用');
            $this->redirect('newPlate');
        }
        // 数据写入
        $data = array(
            'plate_name'=>I('post.plateName'),
            'plate_desc'=>I('post.plateDescription'),
            'plate_create_time'=>strtotime('now'),
        );
        try {
            $result = $newPlate->data($data)->add();
            flash('板块创建成功','green');
            $this->redirect('index');
        } catch(\Exception $e) {
            flash('板块创建失败');
            $this->redirect('newPlate');
        }
    }

    /**
     * 删除板块确认
     */
    public function deletePlate(){
        $delete = M('plate')->where(array('plate_id'=>I('get.pid')))->find();
        $this->assign('delete',$delete);
        $this->display();
    }

    /**
     * 删除板块处理
     *
     * 删除板块时，将bbs_plate表中对应的行删除
     * 并且，将bbs_user_plate_relation表中所有plate_id为删除板块id的行删除
     */
    public function deletePlateHandle(){
        if (!IS_POST) $this->redirect('index');
        $pid = I('post.pid');
        $delete_plate = M('plate');
        $delete_relation = M('user_plate_relation');
        try {
            $delete_plate->where(array('plate_id'=>$pid))->delete();
            $delete_relation->where(array('plate_id'=>$pid))->delete();
            flash('删除成功！', 'green');
        } catch(\Exception $e) {
            flash('删除失败，请重试！');
        }
        $this->redirect('index');
    }

    /**
     * 指定板块的版主管理
     *
     * 以多对多形式实现，有独立的bbs_user_plate_relation表示用户与板块的管理关系
     * 本页面显示指定板块的版主信息
     */
    public function plateAdminManage(){
        if (!empty(I('get.pid'))) {
            // 根据get得到的plate_id连接查询bbs_user_plate_relation表
            $admin = M('user_plate_relation')->where(array('plate_id'=>I('get.pid')))->join('bbs_user ON bbs_user_plate_relation.user_id=bbs_user.user_id')->order('create_time DESC')->select();
            $plate = M('plate')->where(array('plate_id'=>I('get.pid')))->find();
            $this->assign('plate', $plate);
            $this->assign('admin', $admin);
        }
        $this->display();
    }

    /**
     * 增加版主
     */
    public function addAdmin(){
        $pid = I('pid');
        $condition = array(
            'user_admin_group' => array(array('eq', 2), array('eq', 3), 'or'),
        );
        $result = M('user_plate_relation')->join('RIGHT JOIN bbs_user ON bbs_user_plate_relation.user_id=bbs_user.user_id')->where($condition)->select();
        $admin = array();
        foreach ($result as $key=>$value) {
            $index = (int)$value['user_id'];
            $admin[$index] = $value;
            if ($value['plate_id'] == $pid) {
                $admin[$index]['can_admin_plate'] = false;
            } else {
                $admin[$index]['can_admin_plate'] = true;
            }
        }
        $this->assign('addAdmin', $admin);
        $this->assign('plate_id', $pid);
        $this->display();
    }

    /**
     * 新增版主处理
     */
    public function addAdminHandle() {
        if (!IS_POST) $this->redirect('index');
        $pid = I('post.pid');
        $uid_array = I('post.check_all');
        $relation = M('user_plate_relation');
        foreach ($uid_array as $uid) {
            $data = array(
                'user_id' => $uid,
                'plate_id' => $pid,
                'create_time' => strtotime('now'),
            );
            try {
                $relation->data($data)->add();
                flash('添加成功！');
            } catch (\Exception $e) {
                flash('发生错误，请重试！');
            }
        }
        redirect($_SERVER['HTTP_REFERER']);
    }

    /**
     * 删除版主
     *
     * 确认时，将bbs_user_plate_relation中的对应关系查询出来
     * 并显示相关资料
     */
    public function deleteAdmin() {
        $pid = I('get.pid');
        $uid = I('get.uid');
        $condition = array(
            'bbs_user_plate_relation.user_id' => $uid,
            'bbs_user_plate_relation.plate_id' => $pid,
        );
        $relation = M('user_plate_relation');
        $infomation = $relation->join('bbs_user ON bbs_user_plate_relation.user_id=bbs_user.user_id')->join('bbs_plate ON bbs_user_plate_relation.plate_id=bbs_plate.plate_id')->where($condition)->find();
        $this->assign('info', $infomation);
        $this->display();
    }

    /**
     * 删除版主处理
     *
     * 删除时，将bbs_user_plate_relation中的对应关系行删除即可
     */
    public function delAdminHandle(){
        if (!IS_POST) $this->redirect('index');
        $uid = I('post.uid');
        $pid = I('post.pid');
        $relation = M('user_plate_relation');
        $condition = array('user_id' => $uid, 'plate_id' => $pid);
        try {
            $relation->where($condition)->delete();
            flash('删除成功！');
        } catch(\Exception $e) {
            flash('删除失败，请重新尝试！');
        }
        $this->redirect('index');
    }
}