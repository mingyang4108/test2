<?php

namespace app\control;

use kali\core\db;
use kali\core\lib;
use kali\core\req;
use kali\core\tpl;
use kali\core\util;
use kali\core\kali;
use kali\core\lib\cls_page;
use kali\core\lib\cls_potato;
use kali\core\lib\cls_msgbox;
use kali\core\lib\cls_analysis;
use app\model\mod_related_personnel;
use app\model\mod_util;
use app\model\mod_case;
use app\model\mod_host_unit;
use app\model\mod_secrecy;
use app\model\mod_flow_rule;
use app\model\mod_archives;
use app\model\mod_official_action;
use common\model\mod_common;
use app\model\mod_cases_authorizer;
use common\model\pub_mod_table;
use common\model\mod_record_type_search;
use common\model\pub_mod_warning;
//use app\model\mod_cases_authorizer;



/**
 * @desc 实例
 * @date 2017-12-02
 *
 * @version $Id$
 */
class ctl_case
{

    /**
     * 构造函数
     * @return void
     */
    public function __construct()
    {
        //cls_profiler::instance()->enable_profiler(true); //打开调试模式
        $this->tmp_table = "#PB#_case_edit_tmp";  //编辑临时表
        $this->file_all = mod_case::filed_all();
        $this->vfield = "id,content,target_id,type,member_id,infor_type,company_id,object_id,project_id,appid,sid,create_time,infor_table_id"; //预警库检索字段
        $this->inv_field = "id,content,case_id,type,member_id,infor_type,infor_table_id,company_id,project_id,appid,sid,create_time,vid,sta,push_time"; //涉案库检索字段
        //当前操作用户数据
        $this->userinfo = kali::$auth->user;
        //所有用户
        $this->all_user = mod_case::get_all_admin();
        //主要操作数据表
        $this->table = '#PB#_case';
        //警种类型（跟业务属性数据一致的？）
        $this->get_unit_type = mod_case::get_unit_type();
        //实例性质
        $this->case_nature = mod_case::get_case_nature();
        //督办状态
        $this->oversee_status = mod_case::set_oversee_status();
        //全国地区
        $this->area = mod_case::get_area();
        //主办单位 && 立案单位为同一份数据（社会单位？）
        $this->host_unit = self::_get_unit();
        //针对对象
        $this->object = mod_case::get_bc_data('#PB#_target_object');
        //来源方式
        $this->source_type = mod_case::get_bc_data('#PB#_source_way');
        //统一实例类型  优化代码(案例类型改为多选了)
        $this->case_type = mod_case::get_bc_data('#PB#_case_type');
        //危机状态
        $this->handle_status = mod_case::get_bc_data('#PB#_handle_status');
        //针对对象相关性基础数据
        //$this->manage_nature = self::get_bc_data('#PB#_target_correlation','1');
        $this->manage_nature = mod_case::get_manage_nature();
        //审核状态
        $this->sta = array(0 => '未受理', 1 => '审核完成', -1 => '已驳回', 9 => '已受理'); //即 0未受理 9已受理即审核中
        //涉案审核状态
        $this->case_sta = ['0' => '待确认', '1' => '已确认，待系统审核', '2' => '已否定，待系统审核', '3' => '系统已确认', '4' => '系统已否定']; //涉案状态
        //立案单位与主办单位基础数据（社会单位）
        //$this->set_unit = self::_get_unit();
        //实例审核nav
        $this->case_audit_nav = array('status' => '规范性审核', 'relativity' => '相关性审核', 'realness' => '真实性审核', 'zd_object' => '针对对象初审', 'zd_object_again' => '针对对象复审');
        //保密等级
        //$this->privacy_level =  self::get_bc_data('#PB#_secrecy'); //array('1'=>'1（最高）','2'=>'2','3'=>'3','4'=>'4','5'=>'5','6'=>'6','7'=>'7','8'=>'8','9'=>'9','10'=>'10（最低）');
        $this->privacy_level = mod_secrecy::get_option();

        $this->secrecy = array_keys($this->privacy_level);
        //实例状态
        $this->file_status = array('1' => '未立案', '2' => '已立案', '3' => '已结案', '4' => '未知');
        //逆向实例状态
        $this->flip_file_status = array_flip($this->file_status);

        //注册对象
        $this->reg_object = array('1' => '注册', '2' => '非注册');
        //来源单位类型
        $this->source_option = array('1' => '客户列表', '2' => '我方单位', '3' => '社会单位', '4' => '非注册单位');
        //客户列表
        $this->customer_list = mod_case::get_bc_member('#PB#_member');
        //获取案件相关性数据
        $this->get_case_target = mod_case::get_case_target(); //案件相关性与单位相关性是同一份数据，但是案件相关性与注册单位相关性没有关系，案件本身有其相关性
        //获取注册单位相关性数据
        //$m_data = db::get_all("Select id,name  From `#PB#_target_correlation` where `isdeleted` = '0'");
        //$m_data = db::select("id,name")->from('#PB#_target_correlation')->where('isdeleted', '=', '0')->execute();
        //获取所有实例类型
        //$c_data = db::get_all("Select id,name  From `#PB#_case_type` WHERE `isdeleted` = '0'");
        //$c_data = db::select("id,name")->from('#PB#_case_type')->where('isdeleted', '=', '0')->execute();
        //我方单位
        $this->host_unit_mine = mod_case::get_host_unit_mine(); //mod_case::get_bc_data('#PB#_host_unit_mine');
        //社会单位
        $this->host_unit = mod_case::get_unit();
        //来源方式
        $this->source_type = mod_case::get_bc_data('#PB#_source_way');

        //$this->tmp_path = PATH_UPLOADS . '/tmp'; 实例没有文件上传了
        tpl::assign('source_type', $this->source_type);
        tpl::assign('customer_list', $this->customer_list);
        tpl::assign('host_unit_mine', $this->host_unit_mine);
        tpl::assign('host_unit', $this->host_unit);
        //tpl::assign('m_data', $m_data);
        tpl::assign('all_user', $this->all_user);
        //tpl::assign('c_data', $c_data);
        tpl::assign('reg_object', $this->reg_object);
        tpl::assign('case_target', $this->get_case_target);
        tpl::assign('customer_list', $this->customer_list);
        tpl::assign('source_option', $this->source_option);
        tpl::assign('manage_nature', $this->manage_nature);  //新增相关性基础数据
        //tpl::assign('host_unit', $this->host_unit);
        tpl::assign('case_type', $this->case_type);         //案件类型
        tpl::assign('handle_status', $this->handle_status);         //案件类型

        tpl::assign('object', $this->object);
        tpl::assign('privacy_level', $this->privacy_level);
        tpl::assign('file_status', $this->file_status);
        tpl::assign('flip_file_status', $this->flip_file_status);
        //tpl::assign('set_unit',$this->set_unit);
        tpl::assign('sta', $this->sta);
        tpl::assign('case_sta', $this->case_sta);
        tpl::assign('oversee_status', $this->oversee_status);
        tpl::assign('unit_type', $this->get_unit_type);
        tpl::assign('case_nature', $this->case_nature);
        tpl::assign('handle_status', $this->handle_status);
        tpl::assign('area', $this->area);
        tpl::assign('case_type_nav', $this->case_type);

    }


    /**
     * @desc 实例列表页
     * $is_doubtful 是否可疑实例
     * $doubtful_audit 是否开启可以实例的审核
     */
    public function index()
    {
        //cls_profiler::instance()->enable_profiler(True);  //开启调试
        $search_types = array("1" => "实例名称", "2" => "系统编号", "3" => "针对对象", "4" => "主办单位", "5" => "业务属性", "6" => "实例编号");
        $case_status_time = req::item('case_status_time', '');
        $casetype = req::item('casetype', 0, 'int');
        $name_order = req::item('name_order', '');
        $casetype_order = req::item('casetype_order', '');
        $status_order = req::item('status_order', '');
        $name = req::item("name");
        $sys_id = req::item("sys_id");    //生成的系统id （100000+自增id）
        $search_type = req::item("search_type"); //搜索类型
        $la_sdate = req::item("la_sdate");
        $la_edate = req::item("la_edate");
        $search_oversee = req::item("search_oversee");
        $province_s = req::item("province");
        $city = req::item("city");
        $area = req::item("area");
        $is_doubtful = req::item('is_doubtful', '');
        $create_user = req::item('create_user'); //我管理的实例
        $exword = req::item('exword', '');
        $back_url = mod_util::uri_string();
        setcookie("back_url", $back_url);
        //默认搜索
        $where = array(
            array('isdeleted', '=', '0'),
            array('status', '!=', '-2'),
        );
        //可疑实例列表
        if (!empty($is_doubtful)) {
            $where[] = array("confirm_doubtful", "=", 1);
        }

        //关注到期实例
        if (!empty($case_status_time)) {
            $where[] = array("follow_date", "!=", '0000-00-00');
        }

        //2018-07-12 新增督办状态搜索
        if (!empty($search_oversee)) {
            //$create_user_str =  " and `create_user`='{$this->userinfo[uid]}' ";
            //只能创建者查看
            if($search_oversee==-1)
            {
                $where[] = array('oversee_status', '=', 0);
            }else{
                $where[] = array('oversee_status', '=', $search_oversee);
            }

        }
        tpl::assign("search_oversee", $search_oversee);
        if (!empty($create_user) && $create_user == 'my') {
            //$create_user_str =  " and `create_user`='{$this->userinfo[uid]}' ";
            //只能创建者查看
            $where[] = array('create_user', '=', $this->userinfo['uid']);
        }
        if (!empty($name)) {
            switch ($search_type) {
                case '1':
                    $where[] = array('name', 'like', "%{$name}%");
                    break;
                case '2':
                    $where[] = array('number', 'like', "%{$name}%");
                    break;
                case '3':

                    $where[] = ['object', 'like', "%$name%"];
                    break;
                case '4':

                    $tb_where = array(
                        array('isdeleted', '=', 0),
                        array('name', 'like', "%{$name}%"),
                    );
                    $arr = db::select("id")->from('#PB#_host_unit')
                        ->where($tb_where)
                        ->execute();
                    if (!empty($arr)) {
                        $ids = array_unique(array_column($arr, 'id')); //合并一维数组并去重
                        $where[] = array('unit', 'in', $ids);
                    } else {
                        $where[] = array('unit', 'in', array(0 => 0));
                    }

                    break;
                case '5':
                    $tb_where = array(
                        array('isdeleted', '=', 0),
                        array('name', 'like', "%{$name}%"),
                    );
                    $arr = db::select("id")->from('#PB#_host_unit_type')
                        ->where($tb_where)
                        ->execute();
                    if (!empty($arr)) {
                        $ids = array_column($arr, 'id'); //合并一维数组
                        $where[] = array('case_unit_type', 'in', $ids);
                    } else {
                        $where[] = array('case_unit_type', 'in', array(0 => 0));
                    }
                    break;
                case '6':
                    //2018-07-23 新增实例编号查询
                    $real_id = ltrim($name, 'C') - 10000;
                    $where[] = array("id", "=", $real_id);
                    break;
            }
        }

        //根据地区搜索
        if (!empty($province_s)) {
            $where[] = array('province', '=', $province_s);
            $pid = $province_s;
            $arr = db::select("id,fullname")->from('#PB#_region')
                ->where('pid', $pid)
                ->execute();
            $city_option = "<option value= >请选择</option>";
            if (!empty($arr)) {
                foreach ($arr as $k => $v) {
                    if (!empty($city) && $city == $v['id']) {
                        $city_option .= "<option selected value=$v[id] >$v[fullname]</option>";
                    } else {
                        $city_option .= "<option value=$v[id] >$v[fullname]</option>";
                    }

                }
            }
        }
        $city_option = !empty($city_option) ? $city_option : '<option value="">城市</option>';
        tpl::assign('city_option', $city_option);
        if (!empty($city)) {
            $where[] = array('city', '=', $city);
            $pid = $city;
            $arr = db::select("id,fullname")->from('#PB#_region')
                ->where('pid', $pid)
                ->execute();
            $area_option = "<option value= >请选择</option>";
            if (!empty($arr)) {
                foreach ($arr as $k => $v) {
                    if (!empty($area) && $area == $v['id']) {
                        $area_option .= "<option selected value=$v[id] >$v[fullname]</option>";
                    } else {
                        $area_option .= "<option value=$v[id] >$v[fullname]</option>";
                    }

                }
            }

        }
        $area_option = !empty($area_option) ? $area_option : '<option value="">区域</option>';
        tpl::assign('area_option', $area_option);
        if (!empty($area)) {
            //$where[] = "  `area`='{$area}' ";
            $where[] = array('area', '=', $area);
        }

        if (!empty($la_sdate) && !empty($la_edate)) {
            $la_stime = strtotime($la_sdate);
            $la_etime = strtotime($la_edate);
            $time_arr = array($la_stime, $la_etime);
            $where[] = array('date', 'between', $time_arr);
        }
        if (!empty($casetype) && $casetype != '-1') {
            $where[] = array("casetype", "like", "%$casetype%");
        } elseif ($casetype == '-1') {
            $where[] = array("casetype", "=", "0");
        }

        //权限优化
        if ($this->userinfo['groups'] != 1) {
            $authorizer_caseid = mod_cases_authorizer::get_case_ids(); //实例授权人实例id数组
            $handel_person = mod_case::get_person_case();           //实例处理人id数组
            $case_arr = array_unique(array_merge($authorizer_caseid, $handel_person));
            $case_arr = !empty($case_arr)?$case_arr:[0];
            $where[] = ["id", "in", $case_arr];
        }

        //默认排序
        $order_by = 'id';
        $sort = 'desc';
        //案例名称排序
        if ($name_order != '') {
            $order_by = 'name';
            $sort = $name_order == 'desc' ? '   desc' : 'asc';
            $name_order = $name_order == 'desc' ? 'asc' : 'desc';
            tpl::assign('name_order', $name_order);
        }
        if ($casetype_order != '') {
            $order_by = 'casetype';
            $sort = $casetype_order == 'desc' ? 'desc' : 'asc';
            $casetype_order = $casetype_order == 'desc' ? 'asc' : 'desc';
            tpl::assign('casetype_order', $casetype_order);
        }
        if ($status_order != '') {
            $order_by = 'status';
            $sort = $status_order == 'desc' ? 'desc' : 'asc';
            $status_order = $status_order == 'desc' ? 'asc' : 'desc';
            tpl::assign('status_order', $status_order);
        }
        if ($case_status_time != '') {
            $order_by = 'case_status_time';
            $sort = 'asc';
        }

        $row = db::select('count(*) AS `count`')
            ->from($this->table)
            ->where($where)
            ->as_row()
            ->execute();
        $pages = cls_page::make($row['count'], 10);
        $list = db::select($this->file_all)->from($this->table)
            ->where($where)
            ->order_by($order_by, $sort)
            ->limit($pages['page_size'])
            ->offset($pages['offset'])
            ->execute();
        tpl::assign('search_types', $search_types);
        tpl::assign('search_type', $search_type);
        //初始化地区获得省级数据
        $province = db::select("id,fullname")->from('#PB#_region')
            ->where('pid', '0')
            ->execute();
        //获得当前province下的所有city
        if (!empty($data['province'])) {
            $city = db::select("id,fullname")->from('#PB#_region')
                ->where('pid', $data['province'])
                ->execute();
            tpl::assign('city', $city);
        }
        //获得当前所有city下的area
        if (!empty($data['city'])) {
            $area = db::select("id,fullname")->from('#PB#_region')
                ->where('pid', $data['city'])
                ->execute();
            tpl::assign('town', $area);
        }
        tpl::assign('la_sdate', $la_sdate);
        tpl::assign('la_edate', $la_edate);
        tpl::assign('province', $province);
        tpl::assign('province_s', $province_s);
        tpl::assign('city', $city);
        tpl::assign('area', $area);
        tpl::assign('casetype', $casetype);
        tpl::assign('case_status_time', $case_status_time);
        //案例搜索
        tpl::assign('search_case', $casetype);
        tpl::assign('name_order', $name_order);
        tpl::assign('create_user', $create_user);
        tpl::assign('is_doubtful', $is_doubtful);
        //案例排序
        tpl::assign('casetype_order', $casetype_order);
        //word导出按钮显示
        tpl::assign('exword', $exword);
        tpl::assign('status_order', $status_order);
        //$data = isset($data) ? $data : ''; //临时处理一下
        tpl::assign('list', $list);
        tpl::assign('pages', $pages['show']);
        tpl::display('case.index.tpl');
    }


    /***
     * 报警实例
     **/
    public function warn()
    {
        //获取所有报警实例的id
        $case_id = db::select("target_id")->from("#PB#_warning_info")->where("target_type", "=", 1)->execute();
        $back_url = mod_util::uri_string();
        setcookie("back_url", $back_url);
        $list = array();
        if (!empty($case_id)) {
            $ids = array_column($case_id, 'target_id'); //合并一维数组
            $where[] = array('id', 'in', $ids);
        } else {
            $where[] = array('id', 'in', [-1]);
        }

        $reqs['sys_id'] = req::item("sys_id", '');
        $reqs['name'] = req::item("name", '');
        $reqs['warn_time_start'] = req::item('warn_time_start', '');
        $reqs['warn_time_end'] = req::item('warn_time_end', '');
        $order_by = $reqs['orderBy'] = req::item('orderBy', 'warn_time');
        $sort = $reqs['sort'] = req::item('sort', 'desc');

        if (!empty($reqs['warn_time_start']) && !empty($reqs['warn_time_end'])) {
            $where[] = ['warn_time', '>=', $reqs['warn_time_start']];
            $where[] = ['warn_time', '=<', $reqs['warn_time_end']];
        }
        if (!empty($reqs['sys_id'])) {
            $real_id = !empty(ltrim($reqs['sys_id'], 'C')) ? ltrim($reqs['sys_id'], 'C') - 10000 : 0;

            $where[] = array("id", "=", $real_id);

        }
        if (!empty($reqs['name'])) {

            $where[] = array("name", "like", "%$reqs[name]%");
        }

        $row = db::select('count(*) AS `count`')
            ->from($this->table)
            ->where($where)
            ->as_row()
            ->order_by($order_by, $sort)
            ->execute();
        $pages = cls_page::make($row['count'], 10);
        $list = (array)db::select($this->file_all)->from($this->table)
            ->where($where)
            ->order_by($order_by, $sort)
            ->limit($pages['page_size'])
            ->offset($pages['offset'])
            ->execute();

        tpl::assign('search', $reqs);
        tpl::assign('list', $list);
        tpl::assign('pages', $pages['show']);
        tpl::display('case.warn.index.tpl');


    }

    /**
     * @desc 实例详情页
     * */
    public function detail()
    {
        //这里设置返回一下基础实例基础链接的链接，不然在规范性审核的时候会报错
        $base_url = mod_util::uri_string();
        setcookie('base_url', $base_url);
        $id = req::item('case_id', 0, 'int');
        $case_status_time = req::item('case_status_time', ''); //关注实例进来的
        $secc = req::item('secc', ''); //审核页面进来的不检测权限
        $warm = req::item('warm', '');
        $data = db::select($this->file_all)->from('#PB#_case')->where('id', $id)->as_row()->execute();
        if (empty($data)) {
            cls_msgbox::show('系统提示', '数据不存在！', '-1');
            exit();
        }
        //超级管理员不做任何权限限制
        if ($this->userinfo['groups'] != 1) {
            $msg = mod_cases_authorizer::confirm_request($id);
            //非超级管理员要验证与我提交的不受授权权限控制
            if ($this->userinfo['groups'] != 1 && $this->userinfo['uid'] != $data['create_user'] && $secc != '1') {
                if ($msg['status'] != true) {
                    cls_msgbox::show('系统提示', $msg['document'], $msg['url']);
                }
            }
        }
        $object_infos = db::select('mn_id')->from('#PB#_target_object')->where('id', $data["object"])->as_row()->execute();
        $data['mn_id'] = '';
        //根据针对对象获取对应相关性
        if (!empty($object_infos)) {
            $data['mn_id'] = $object_infos['mn_id'];
        }
        $data['create_time'] = date('Y-m-d', $data['create_time']);

        $timestamp = KALI_TIMESTAMP;
        $token = md5('unique_salt' . $timestamp);
        $typeback = req::item('typeback', '');
        $forback = req::item('forback', '');  //控制返回页面
        if (empty($typeback)) {
            $backurl = $forback == 'index' ? '?ct=case&ac=index' : 'javascript:history.back(-1)';
        } else {
            $backurl = $typeback == 'about' ? '?ct=case&ac=about_case' : 'javascript:history.back(-1)';
        }
        $unit_info = db::select("province,city")->from("#PB#_host_unit")->where("id", $data['unit'])->as_row()->execute();
        $data['unit_province'] = $unit_info['province'];
        $data['unit_city'] = $unit_info['city'];
        $data['oversee_unit'] = !empty($data['oversee_unit']) ? explode("、", $data['oversee_unit']) : '';
        $involved_log = self::_inv_list_log(pub_mod_table::INVOLVED_LOG, $data);
        //线索库上传的json字段
        $data['unreg_arr'] = !empty($data['unreg_field'])?json_decode($data['unreg_field'],true):0;

//        echo "<pre />";
//        print_r($data);
        tpl::assign('involved_log', $involved_log);
        tpl::assign('typeback', $typeback);
        tpl::assign('warm', $warm);
        tpl::assign('area', $this->area);  //地区列表
        tpl::assign('groups', $this->userinfo['groups']);
        tpl::assign('user_id', $this->userinfo['uid']);
        tpl::assign('case_status_time', $case_status_time);
        tpl::assign('backurl', $backurl);
        tpl::assign('timestamp', $timestamp);
        tpl::assign('token', $token);
        tpl::assign('ct', req::item('ct'));
        tpl::assign('data', $data);
        tpl::display('case.detail.tpl');
    }
    /**
     * ajax修改线索库上传的未确定字段
     **/
    public function ajax_edit_field()
    {
        $id = req::item('id',0); //实例id
        $rows = db::select("unreg_field")->from(pub_mod_table::CASE)->where("id",$id)->as_row()->execute();
        $rows_arr = json_decode($rows['unreg_field'],true);  //转换为数组
        $field = req::item('field'); //要修改的字段

//        if($field=='casetype') //实例类型
//        {
//            $field_key = 'case_type';
//            unset($rows_arr[$field_key]);
//            $rows_json = json_encode($rows_arr);
//
//        }elseif($field=='case_unit_type') //实例属性
//        {
//            //实例类型
//            $field_key = 'case_attr';
//            unset($rows_arr[$field_key]);
//            $rows_json = json_encode($rows_arr);
//        }else

        if($field=='filing_unit') //立案单位
        {
            $field_key = 'filing_unit_unreg';
            unset($rows_arr[$field_key]);
            $rows_json = json_encode($rows_arr,JSON_UNESCAPED_UNICODE);
        }elseif($field=='unit') //主办单位
        {
            $field_key = 'unit_unreg';
            unset($rows_arr[$field_key]);
            $rows_json = json_encode($rows_arr,JSON_UNESCAPED_UNICODE);
        }
        $content = req::item('content'); //要修改的内容
        $update_data = [
            $field=>$content,
            'unreg_field'=>$rows_json
        ];
        $res = db::update(pub_mod_table::CASE)->set($update_data)->where("id",$id)->execute();
        if($res)
        {
            echo 1;
        }else{
            echo -1;
        }
    }
    /**
     * 可疑实例审核列表 1.选择了疑似实例的 2.实例处理人能审核的
     **/
    public function case_doubtful()
    {
        $search_types = array("1" => "实例名称", "4" => "主办单位");
        $casetype = req::item('casetype', 0, 'int');
        $audit = req::item('audit', '');
        $secc = req::item('secc', '');
        $is_audit = 'is_' . $audit;
        $is_audit_val = req::item($is_audit, '');
        $status = req::item('status');
        $create_user = req::item('create_user');
        $search_type = req::item("search_type");
        $back_url = mod_util::uri_string();
        setcookie("back_url", $back_url);
        //默认搜索，选了可疑实例的才会进来
        $where =
            array(
                array('isdeleted', '=', 0),

            );
        //除了驳回，其他都是可疑实例才能查看，驳回的时候已经重置了非可疑实例
        if ($status != '-1') {
            $where[] = array('is_doubtful', '=', 1);
        }

        //我提交的
        if (!empty($create_user) && $create_user == 'my') {
            $where[] = array('create_user', '=', $this->userinfo['uid']);
        }
        //待受理
        if ($status == '0') {
            $where[] = array('confirm_doubtful', '=', $status);
        }
        //已受理待审核
        if ($status == '9') {
            $where[] = array('confirm_doubtful', '=', $status);
        }
        //已完成
        if ($status == '1') {
            $where[] = array('confirm_doubtful', '=', $status);
        }

        if ($status == '-1') {
            $where[] = array('confirm_doubtful', '=', $status);
        }

        if (!empty($casetype) && $casetype != '-1') {
            $where[] = array("casetype", "like", "%$casetype%");
        } elseif ($casetype == '-1') {
            $where[] = array("casetype", "=", "0");
        }
        //权限优化
        if ($this->userinfo['groups'] != 1) {
            $authorizer_caseid = mod_cases_authorizer::get_case_ids(); //实例授权人实例id数组
            $handel_person = mod_case::get_person_case();           //实例处理人id数组
            $case_arr = array_unique(array_merge($authorizer_caseid, $handel_person));
            $where[] = ["id", "in", $case_arr];
        }

        //默认排序
        $order_by = 'id';
        $sort = 'desc';
        $row = db::select('count(*) AS `count`')
            ->from($this->table)
            ->where($where)
            ->as_row()
            ->execute();
        $pages = cls_page::make($row['count'], 10);
        $list = db::select($this->file_all)->from($this->table)
            ->where($where)
            ->order_by($order_by, $sort)
            ->limit($pages['page_size'])
            ->offset($pages['offset'])
            ->execute();

//        if ($this->userinfo['groups'] != 1 && $create_user != 'my') {
//            //不是超管的话只有实例处理人可见
//            if (!empty($list)) {
//                foreach ($list as $k => $v) {
//                    if (mod_case::is_case_handle_man($this->userinfo['uid'], $v['case_handle_man']) == true) {
//                        $data[$k] = $v;
//                    }
//                }
//                $pages = cls_page::make(@count($list), 20); //php7.2会报$list为无效参数
//            }
//        } else {
//
//            $data = $list;
//        }

        //案例搜索
        tpl::assign('search_case', $casetype);
        tpl::assign('status', $status);
        tpl::assign('create_user', $create_user);
        tpl::assign('search_types', $search_types);
        tpl::assign('search_type', $search_type);
        tpl::assign('pages', $pages['show']);
        tpl::assign('is_audit', 'is_' . $audit);
        tpl::assign('audit', $audit);
        tpl::assign('status', $status);
        tpl::assign('casetype', $casetype);
        tpl::assign('nav_data', $this->case_audit_nav);
        tpl::assign('list', $list);
        tpl::assign('pages', $pages['show']);
        tpl::display('case.case_doubtful.tpl');

    }

    /**
     * 可疑性审核处理
     *
     **/
    public function case_doubtful_detail()
    {

        if (req::$posts) {
            $id = req::item("id");
            //确认可疑
            if (req::$posts["audit"] == '1') {
                $case_detail = 'bj_status_9';
                //修改状态为确认可疑
                db::update($this->table)->set(["confirm_doubtful" => 1])->where('id', $id)->execute();
                $gourl = "?ct=case&ac=case_doubtful&status=9";
                cls_msgbox::show('系统提示', '审核成功，可在可疑实例列表查看相关数据！', $gourl);
            } elseif (req::$posts["audit"] == '-1') {
                $case_detail = 'bj_status_9';
                $update_data = array(
                    "confirm_doubtful" => -1,
                    "is_doubtful" => 0  //重置可疑实例为否
                );
                $gourl = "?ct=case&ac=case_doubtful&status=9&case_detail=".$case_detail;
                $res = db::update($this->table)->set($update_data)->where('id', $id)->execute();
                cls_msgbox::show('系统提示', '驳回成功，此实例确认为不可疑！', $gourl);
            } else {
                cls_msgbox::show('系统提示', '请选择审核状态');
            }
        }
        $id = req::item('case_id', 0, 'int');
        $status = req::item('status', '');
        $audit = req::item('filed', '');
        $data = db::select($this->file_all)->from($this->table)->where('id', $id)->as_row()->execute();
        if (empty($data)) {
            cls_msgbox::show('系统提示', '数据不存在！', '-1');
            exit();
        }
        $object_infos = db::select("mn_id,name")->from('#PB#_target_object')->where('id', $data["object"])->as_row()->execute();

        $data["object_name"] = $object_infos["name"];

        $flow_info = array();
        $audit_type = '';
        if ($audit == 'status') {
            $audit_type = '1';
        } else {
            $audit_type = '0';
        }
        $flow_info = mod_flow_rule::flow_get_data($audit_type, $id, $audit, 'all', '1');

        if ($flow_info) {
            foreach ($flow_info as $k => $vv) {
                $flow_info[$k]['check_user'] = mod_case::get_username($vv['check_user']);
            }
        }

        $timestamp = KALI_TIMESTAMP;
        //多选后的实例处理人
        $case_handle_man = explode('、', $data['case_handle_man']);
        $unit_info = db::select("province,city")->from("#PB#_host_unit")->where("id", $data['unit'])->as_row()->execute();
        $data['unit_province'] = $unit_info['province'];
        $data['unit_city'] = $unit_info['city'];
        tpl::assign('case_handle_man', $case_handle_man);
        tpl::assign('status', $status);
        //多选后的实例编辑类型
        $case_type_arr = explode('、', $data['casetype']);
        tpl::assign('case_type_arr', $case_type_arr);
        $token = md5('unique_salt' . $timestamp);
        //tpl::assign('mn_data', $mn_data);
        tpl::assign('flow_info', $flow_info);
        //tpl::assign('province', $province);
        tpl::assign('timestamp', $timestamp);
        tpl::assign('token', $token);
        tpl::assign('ct', req::item('ct'));
        tpl::assign('data', $data);
        tpl::assign('audit', $audit);
        tpl::assign('nav_data', $this->case_audit_nav);
        tpl::display('case_doubtful_detail.tpl');
    }

    /**
     * 独立的相关性修改
     **/
    public function case_edit_relativity()
    {
        $id = req::item('id');
        $data = req::$posts;
        if (!empty($data)) {
            $manage_nature = $data['manage_nature'];
            $array['manage_nature'] = $manage_nature;
            db::update($this->table)->set($array)->where('id', $data['id'])->execute();
            cls_msgbox::show('系统提示', '相关性修改成功', '-1');
        } else {
            $where = array(
                array('id', '=', $id),
                array('isdeleted', '=', 0)
            );
            $rows = db::select($this->file_all)
                ->from($this->table)
                ->where($where)
                ->as_row()
                ->execute();
            if (empty($rows)) {
                cls_msgbox::show('系统提示', '非法数据，请离开');
            }

            tpl::assign('data', $rows);
            tpl::display('case.edit.relativity.tpl');
        }


    }


    /**
     *导出case基本信息
     */
    public function case_explode_word()
    {
        $id = req::item('id');
        if (empty($id)) {
            exit('非法操作，请离开');
        }
        $data = db::select($this->file_all)->from($this->table)->where('id', $id)->as_row()->execute();
        //数据格式化
        $data['name'] = $data['name'] ? $data['name'] : ''; //案例名称

        $data['manage_nature'] = array_key_exists($data['manage_nature'], $this->manage_nature) == true ? $this->manage_nature[$data['manage_nature']] : '暂无';

        $data['privacy_level'] = $this->privacy_level[$data['privacy_level']];

        if ($data['reg_object'] == '1') {
            $data['object'] = array_key_exists($data['object'], $this->object) == true ? $this->object[$data['object']] : '暂无';
        } else {
            $data['object'] = !empty($data['not_reg_object']) ? $data['not_reg_object'] : '暂无';
        }

        $data['tasknum'] = !empty($data['tasknum']) ? $data['tasknum'] : '暂无';

        $data['info'] = !empty($data['info']) ? $data['info'] : '暂无';

        $data['number'] = !empty($data['number']) ? $data['number'] : '暂无';

        $data['case_nature'] = array_key_exists($data['case_nature'], $this->case_nature) == true ? $this->case_nature[$data['case_nature']] : '无';

        $data['manage_nature'] = !empty($data['manage_nature']) ? $data['manage_nature'] : '无';

        $data['filing_unit'] = array_key_exists($data['filing_unit'], $this->host_unit) == true ? $this->host_unit[$data['filing_unit']] : '无';

        $data['unit'] = array_key_exists($data['unit'], $this->host_unit) == true ? $this->host_unit[$data['unit']] : '无';

        $data['file_status'] = array_key_exists($data['file_status'], $this->file_status) == true ? $this->file_status[$data['file_status']] : '无';

        $data['oversee_status'] = array_key_exists($data['oversee_status'], $this->oversee_status) == true ? $this->oversee_status[$data['oversee_status']] : '无';

        $data['handle_status'] = array_key_exists($data['handle_status'], $this->handle_status) == true ? $this->handle_status[$data['handle_status']] : '无';

        $data['case_unit_type'] = array_key_exists($data['case_unit_type'], $this->get_unit_type) == true ? $this->get_unit_type[$data['case_unit_type']] : '无';

        $data['case_handle_man'] = mod_case::get_handle_man($data['case_handle_man']);

        $data['casetype'] = mod_case::get_case_type_name($data['casetype']);

        $data['project_num'] = !empty($data['project_num']) ? $data['project_num'] : '无';

        $data['case_info'] = !empty($data['case_info']) ? $data['case_info'] : '无';
        //默认条件
        $where = array(
            array('case_id', '=', $data['id']),
            array('isdeleted', '=', 0),
        );

        //协查信息 收件单位、协查标题
        //$assist_info = mod_case::get_field_data("assist","title,unit",$where);

        //实例导出名称
        $docname = mod_case::replace_special_char($data['name']) . '.docx';
        //加载PHPWord文件`
        //require '../core/library/phpword/PHPWord.php';
        //echo kali::$base_root . '/../common/lib/phpword/PHPWord.php';
        require_once(kali::$base_root . '/../common/lib/phpword/PHPWord.php');
        //初始化对象
        $PHPWord = new \PHPWord();
        //创建一个页面
        $section = $PHPWord->createSection();
        //叶眉
        $header = $section->createHeader();
        //$header->addImage( $upload_dir.'111.png');
        //定义表格样式
        $styleTable = array('bold' => true, 'borderSize' => 2, 'borderColor' => '006699', 'cellMargin' => 300);

        //无边框样式
        $styleFirstRow = array('borderBottomSize' => 2, 'borderBottomColor' => '0000FF');  //列样式

        $styleCell = array('valign' => 'center');

        $styleCellLabel = array('bold' => true);

        $styleCell2 = array('valign' => 'center', 'borderSize' => 0);
        //合并单元格
        $styleCellCospan = array('cellMerge' => 'restart', 'valign' => "center", 'bgColor' => 'C4C4C4');

        //无颜色的合并单元格
        $styleCellCospan2 = array('cellMerge' => 'restart', 'valign' => "center");

        $styleCellMerge = array('cellMerge' => 'continue');

        $styleCellBTLR = array('valign' => 'center', 'textDirection' => \PHPWord_Style_Cell::TEXT_DIR_BTLR);

        //定义字体样式
        $fontStyle = array('align' => 'center');

        $fontStyle2 = array('size' => 12, 'bold' => false);

        //二级标题
        $fontTitle2 = array('align' => 'left', 'size' => '16', 'bgColor' => '66ffff');

        //三级标题
        $fontTitle3 = array('align' => 'left', 'size' => '14', 'bold' => true);

        //定义标题样式
        $titleStyle = array('align' => 'left', 'bold' => true, 'size' => '22', 'valign' => 'center', 'cellMerge' => 'restart');

        $styleTabel2 = array('bold' => true, 'borderSize' => 1, 'borderColor' => '006699', 'cellMargin' => 300);

        $styleFirstRow2 = array('borderBottomSize' => 0, 'borderBottomColor' => '0000FF', 'bgColor' => '66ffff');  //列样式

        //加载表格样式
        $PHPWord->addTableStyle('myOwnTableStyle', $styleTable, $styleFirstRow);
        $PHPWord->addTableStyle('myOwnTableStyle', $styleTable, $styleFirstRow);
        $PHPWord->addTitleStyle(6, $titleStyle);
        $section->addTitle(iconv('utf-8', 'GB2312//IGNORE', $data['name']), 6);

        //分隔符号
        $section->addTextBreak(2);

        $section->addText(iconv('utf-8', 'GB2312//IGNORE', '案件信息'), $fontTitle2);

        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "1.立案单位:{$data['filing_unit']}"), $fontTitle3);

        $date = date('Y年m月d日', $data['date']);
        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "2.立案时间:{$date}"), $fontTitle3);
        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "3.主办单位:{$data['unit']}"), $fontTitle3);
        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "4.督办单位:{$data['oversee_unit']}"), $fontTitle3);
        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "5.案件性质:{$data['casetype']}"), $fontTitle3);
        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "6.案件详情:"), $fontTitle3);
        $section->addText(iconv('utf-8', 'GB2312//IGNORE', $data['case_info']), $fontStyle2);
        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "7.针对对象:{$data['object']}"), $fontTitle3);
        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "8.涉案金额:"), $fontTitle3);

        $section->addTextBreak(1);

        $section->addText(iconv('utf-8', 'GB2312//IGNORE', '办案单位掌握的线索'), $fontTitle2);
        //证据库start----------

        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "1.协查信息"), $fontTitle3);

        $assist_contents = (array)db::select([
            'type', 'content'
        ])->from('#PB#_assist_info')->where('case_id', $data['id'])->where('delete_time', 0)->execute();
        $arr = [];
        foreach ($assist_contents as $k => $value) {
            $arr[$value['type']][] = $value['content'];
        }
        $assist_type = mod_record_type_search::$type;

        foreach ($arr as $k => $value) {
            $str = is_array($value) ? implode(';', $value) : $value;
            $section->addText(iconv('utf-8', 'GB2312//IGNORE', "{$assist_type[$k]}:{$str}"), $fontStyle2);
        }

        //-----------证据库end

        //初始化表格
//        $table = $section->addTable('myOwnTableStyle');
//
//        $table->addRow(100);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '基本信息'), $fontStyle);
//        $table->addCell(0, $styleCellMerge);
//        $table->addCell(0, $styleCellMerge);
//        $table->addCell(0, $styleCellMerge);
//
//        $table->addRow(500);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '相关性'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['manage_nature']), $fontStyle);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '保密等级'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['privacy_level']), $fontStyle);
//
//        $table->addRow(500);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '针对对象'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['object']), $fontStyle);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '危机任务号'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['tasknum']), $fontStyle);
//
//        $table->addRow(500);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '实例简述'), $fontStyle);
//        $table->addCell(7500, array('cellMerge' => 'restart', 'valign' => "center"))->addText(iconv('utf-8', 'GB2312//IGNORE', $data['info']), $fontStyle);
//        $table->addCell(0, array('cellMerge' => 'continue'));
//        $table->addCell(0, array('cellMerge' => 'continue'));
//        $table->addRow(100);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '原始案件信息'), $fontStyle);
//        $table->addCell(0, $styleCellMerge);
//        $table->addCell(0, $styleCellMerge);
//        $table->addCell(0, $styleCellMerge);
//
//        $table->addRow(500);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '系统编号'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['number']), $fontStyle);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '实例性质'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['case_nature']), $fontStyle);
//
//
//        $table->addRow(500);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '立案单位'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['filing_unit']), $fontStyle);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '主办单位'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['unit']), $fontStyle);
//
//        $table->addRow(500);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '实例状态'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['file_status']), $fontStyle);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '督办状态'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['oversee_status']), $fontStyle);
//
//        $table->addRow(500);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '实例类型'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['casetype']), $fontStyle);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '危机状态'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['handle_status']), $fontStyle);
//
//        $table->addRow(500);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '业务属性'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['case_unit_type']), $fontStyle);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '实例处理人'), $fontStyle);
//        $table->addCell(3000, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', $data['case_handle_man']), $fontStyle);
//
//        $table->addRow(500);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '项目号'), $fontStyle);
//        $table->addCell(7500, array('cellMerge' => 'restart', 'valign' => "center"))->addText(iconv('utf-8', 'GB2312//IGNORE', $data['project_num']), $fontStyle);
//        $table->addCell(0, array('cellMerge' => 'continue'));
//        $table->addCell(0, array('cellMerge' => 'continue'));
//
//        $table->addRow(500);
//        $table->addCell(1500, $styleCell)->addText(iconv('utf-8', 'GB2312//IGNORE', '实例详情'), $fontStyle);
//        $table->addCell(7500, array('cellMerge' => 'restart', 'valign' => "center"))->addText(iconv('utf-8', 'GB2312//IGNORE', $data['case_info']), $fontStyle);
//        $table->addCell(0, array('cellMerge' => 'continue'));
//        $table->addCell(0, array('cellMerge' => 'continue'));

        //------------------相关人员-------------------
        //获取相关人员需显示的信息
        $personel_list = mod_related_personnel::get_person_info($id);

        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "2.人员信息"), $fontTitle3);
//        $PHPWord->addTableStyle('myOwnTableStyle3', $styleTable, $styleFirstRow);
//        $PHPWord->addTitleStyle(6, $titleStyle);
//        $section->addTextBreak(2);
//        $table = $section->addTable('myOwnTableStyle3');
//        $table->addRow(200);
//        $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '相关人员'), $titleStyle);
//        $table->addCell(0, $styleCellMerge);
//
//        $table->addRow(50);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '相关人员'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);

        if (!empty($personel_list)) {
            $arr = [];
            foreach ($personel_list as $k => $value) {
                if (in_array($value['wanted'], [2, 3])) {
                    $arr[$value['wanted']][] = $value;
                }
            }
            $section->addText(iconv('utf-8', 'GB2312//IGNORE', "上网人员:"), $fontStyle2);
            $i = 0;
            foreach ($arr as $k => $value) {
                if ($k == 3) {
                    $i++;
                    $section->addText(iconv('utf-8', 'GB2312//IGNORE', "{$i}.姓名:{$value['name']} 籍贯: 身份证: {$value['certificate']}"), $fontStyle2);
                }
            }
            $section->addText(iconv('utf-8', 'GB2312//IGNORE', "撤网人员:"), $fontStyle2);
            $i = 0;
            foreach ($arr as $k => $value) {
                if ($k == 2) {
                    $i++;
                    $section->addText(iconv('utf-8', 'GB2312//IGNORE', "{$i}.姓名:{$value['name']} 籍贯: 身份证: {$value['certificate']}"), $fontStyle2);
                }
            }
//            foreach ($personel_list as $v5) {

//                $v5['address'] = !empty($v5['address']) ? $v5['address'] : '不明';
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '姓名:' . $v5['name']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
//
//                $table->addRow(100);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '代号:' . $v5['code_name']), $fontStyle);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '称呼:' . $v5['call_name']), $fontStyle);
//
//                $table->addRow(100);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '曾用名:' . $v5['used_name']), $fontStyle);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '人员类型:' . $v5['type_name']), $fontStyle);
//
//                $table->addRow(100);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '国籍:' . $v5['nationality']), $fontStyle);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '性别:' . $v5['gender']), $fontStyle);
//
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '出生日期:' . $v5['birthday']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '证件类型:' . $v5['certificate']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '联系方式:' . $v5['contact']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '常住地址:' . $v5['address']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '机构:' . $v5['organization']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '部门:' . $v5['department']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '人员背景:' . $v5['background']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//            }
        }


        //----------相关人员end-----------
//
//        $PHPWord->addTableStyle('myOwnTableStyle2', $styleTable, $styleFirstRow);
//        $PHPWord->addTitleStyle(6, $titleStyle);
//        $section->addTextBreak(2);
//        $table = $section->addTable('myOwnTableStyle2');
//
//        $table->addRow(200);
//        $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '档案库'), $titleStyle);
//        $table->addCell(0, $styleCellMerge);
//        $where_arr = array(["type", "=", '1'], ["isdeleted", "=", '0'], ["case_id", "=", $id]);
        $order_where = ["id", "desc"];
//        $evidence_arr = array("1" => "人证", "2" => "物证", "3" => "电子物证");
//        $archives = mod_archives::get_all("*", $where_arr, $order_where);
//        //涉及人员
//        if (!empty($archives["other_id"]["ids"])) {
//            $archives_ids = $archives["other_id"]["ids"];
//            $personnel_ids = mod_archives_personnel::get_personnel_ids([["archives_id", "in", $archives_ids]]);
//            $personnel_id_str = implode(",", $personnel_ids["personnel_ids"]);
//            $personnel_arr = mod_archives_personnel::get_personnels($personnel_id_str);
//
//        }

        //证据库start----------
//        $table->addRow(100);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '证据'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);
//        if (!empty($archives['list'])) {
//            foreach ($archives['list'] as $v) {
//                //获得涉及人员
//                if (array_key_exists($v['id'], $personnel_ids['archives_info'])) {
//                    $personnel_name = mod_case::format_posernel($personnel_ids['archives_info'][$v['id']], $personnel_arr);
//                } else {
//                    $personnel_name = '-';
//                }
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '名称:' . $v['name']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '分类:' . $evidence_arr[$v['category']]), $fontStyle);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '采集日期:' . date('Y-m-d', $v['collect_time'])), $fontStyle);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '涉及人员:' . $personnel_name), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//            }
//        }


        //----------证据库end

        //----------口供start
//        $table->addRow(100);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '口供'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);
        $section->addTextBreak(1);
        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "3.口供信息"), $fontTitle3);

        $where_arr2 = array(["type", "=", "2"], ["isdeleted", "=", "0"], ["case_id", "=", $id]);
        $archives2 = mod_archives::get_all("*", $where_arr2, $order_where);
        //涉及人员
        if (!empty($archives2["other_id"]["ids"])) {
            $archives_ids2 = $archives2["other_id"]["ids"];
            $personnel_ids2 = mod_archives_personnel::get_personnel_ids([["archives_id", "in", $archives_ids2]]);
            $personnel_id_str2 = implode(",", $personnel_ids2["personnel_ids"]);
            $personnel_arr2 = mod_archives_personnel::get_personnels($personnel_id_str2);

        }
        if (!empty($archives2["other_id"]["confession_peoples"])) {
            $confession_peoples_ids = $archives2["other_id"]["confession_peoples"];
//
            $infos = db::select("`id`, `family_name`, `first_name`, `call_name`, `code_name`, `used_name`")
                ->from("#PB#_related_personnel")
                ->where([["id", "in", $confession_peoples_ids]])
                ->execute();
//
            $confession_peoples_arr = array();
            foreach ($infos as $key => $value) {
                $name = "";
                if (!empty($value)) {
                    if (!empty($value["family_name"] . $value["first_name"])) {
                        $name = $value["family_name"];
                    } elseif (!empty($value["call_name"])) {
                        $name = $value["call_name"];
                    } elseif (!empty($value["code_name"])) {
                        $name = $value["code_name"];
                    } elseif (!empty($value["used_name"])) {
                        $name = $value["used_name"];
                    }
                }
                $confession_peoples_arr[$value["id"]] = $name;
            }
//
            foreach ($archives2['list'] as $v2) {
                if (array_key_exists($v2['id'], $personnel_ids2['archives_info'])) {
                    $personnel_name2 = mod_case::format_posernel($personnel_ids2['archives_info'][$v2['id']], $personnel_arr2);
                } else {
                    $personnel_name2 = '-';
                }
                $section->addText(iconv('utf-8', 'GB2312//IGNORE', "口供名称:{$v2['name']}"), $fontStyle2);
                $section->addText(iconv('utf-8', 'GB2312//IGNORE', "供述人:{$confession_peoples_arr[$v2['confession_people']]}   供述日期:" . date('Y-m-d', $v2['collect_time'])), $fontStyle2);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '名称:' . $v2['name']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '供述人:' . $confession_peoples_arr[$v2['confession_people']]), $fontStyle);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '口供日期:' . date('Y-m-d', $v2['collect_time'])), $fontStyle);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '涉及人员:' . $personnel_name2), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
            }
        }

        //证据库end---------
        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "4.汇报材料"), $fontTitle3);

        $where_arr3 = array(["type", "=", "3"], ["isdeleted", "=", "0"], ["case_id", "=", $id]);
        $archives3 = mod_archives::get_all("*", $where_arr3, $order_where);
//
//        //----------汇报材料start
//        $table->addRow(100);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '汇报材料'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);
        if (!empty($archives3['list'])) {
            foreach ($archives3['list'] as $v3) {
                $section->addText(iconv('utf-8', 'GB2312//IGNORE', "材料名称:{$v3['name']}"), $fontStyle2);
                $section->addText(iconv('utf-8', 'GB2312//IGNORE', "材料分类:" . mod_report_stuff_type::get_name($v3['category']) . "   汇报日期:" . date('Y-m-d', $v3['collect_time'])), $fontStyle2);
//
//                $info = mod_archives::verification_secrecy($id, $v3['id'], "3");
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '名称:' . $v3['name']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '分类:' . mod_report_stuff_type::get_name($info["category"])), $fontStyle);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '汇报日期:' . date("Y-m-d", $v3['collect_time'])), $fontStyle);
            }
        }

        //汇报材料end---------

        //----------附件start
//        $table->addRow(100);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '附件'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);
//        $where_arr4 = array(["type", "=", "4"], ["isdeleted", "=", "0"], ["case_id", "=", $id]);
//        $archives4 = mod_archives::get_all("*", $where_arr4, $order_where);
//        if (!empty($archives4['list'])) {
//            foreach ($archives4['list'] as $v4) {
//                $info2 = mod_archives::verification_secrecy($id, $v4['id'], "4");
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '名称:' . $v4['name']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '分类:' . mod_attachment_type::get_name($info2["category"])), $fontStyle);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '上传日期:' . date('Y-m-d', $v4['create_time'])), $fontStyle);
//            }
//        }
        //档案附件end---------


        //---------- 管理信息 start -------------
        //获取管理信息相关数据
//        $clue_where[] = ['case_id', '=', $id];
//        $clue_where[] = ['isdeleted', '=', '0'];
//        $clue_info = $list = db::select("id,name,FROM_UNIXTIME(`create_time`,'%Y-%m-%d') As `create_time`,date,status,create_user,info")
//            ->from('#PB#_clue')
//            ->where($clue_where)
//            ->order_by('id', 'asc')
//            ->execute();
//        //管理信息状态
//        $options = array(
//            1 => '未确认',
//            2 => '已确认',
//            3 => '跟踪中',
//            4 => '无效管理信息',
//            5 => '已得结论'
//        );
//        $PHPWord->addTableStyle('myOwnTableStyle4', $styleTable, $styleFirstRow);
//        $PHPWord->addTitleStyle(6, $titleStyle);
//        $section->addTextBreak(2);
//        $table = $section->addTable('myOwnTableStyle4');
//        $table->addRow(200);
//        $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '管理信息'), $titleStyle);
//        $table->addCell(0, $styleCellMerge);
//
//        $table->addRow(50);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '管理信息'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);
//        if (!empty($clue_info)) {
//            foreach ($clue_info as $v6) {
//                $v6['info'] = !empty($v6['info']) ? $v6['info'] : '无';
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '管理信息名称:' . $v6['name']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
//                $table->addRow(100);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '管理信息状态:' . $options[$v6['status']]), $fontStyle);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '掌握日期:' . $v6['date']), $fontStyle);
//
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '管理信息详情:' . $v6['info']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//            }
//        }


        //---------- 管理信息 end -------------

        //----------协查信息 start-----------------
//        $assist_list = mod_explode_word::get_list($id, "record_type_search", "title,time,request,number");
//
//        $PHPWord->addTableStyle('myOwnTableStyle5', $styleTable, $styleFirstRow);
//        $PHPWord->addTitleStyle(6, $titleStyle);
//        $section->addTextBreak(2);
//        $table = $section->addTable('myOwnTableStyle5');
//        $table->addRow(200);
//        $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '协查信息'), $titleStyle);
//        $table->addCell(0, $styleCellMerge);
//
//        $table->addRow(50);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '协查信息'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);
//        if (!empty($assist_list)) {
//            foreach ($assist_list as $v7) {
//                $v7['time'] = !empty($v7['time']) ? $v7['time'] : '无';
//                $v7['request'] = !empty($v7['request']) ? $v7['request'] : '无';
//                $v7['title'] = !empty($v7['title']) ? $v7['title'] : '无';
//                $v7['number'] = !empty($v7['number']) ? $v7['number'] : '无';
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '协查日期:' . $v7['time']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '协查编号:' . $v7['number']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '协查标题:' . $v7['title']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '协查内容:' . $v7['request']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//            }
//        }

        //----------协查信息 end-----------------

        //----------信息来源 start-----------------
//        $source_list = mod_explode_word::get_list($id, "sourcus", "sourcus,sourcus_type,update_time,create_time,source_option");
//
//        $PHPWord->addTableStyle('myOwnTableStyle6', $styleTable, $styleFirstRow);
//        $PHPWord->addTitleStyle(6, $titleStyle);
//        $section->addTextBreak(2);
//        $table = $section->addTable('myOwnTableStyle6');
//        $table->addRow(200);
//        $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '信息来源'), $titleStyle);
//        $table->addCell(0, $styleCellMerge);
//
//        $table->addRow(50);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '信息来源'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);
//        if (!empty($source_list)) {
//            foreach ($source_list as $v8) {
//                $table->addRow(100);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '来源单位:' . $v8['sourcus']), $fontStyle);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '来源方式:' . $v8['sourcus_type']), $fontStyle);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '更新日期:' . $v8['update_time']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//            }
//        }

        //----------信息来源 end-----------------

        //----------疑点与核实 start-----------------
//        $verify_list = mod_explode_word::get_list($id, "verify", "id,title,even_id,relation");
//
//        $PHPWord->addTableStyle('myOwnTableStyle7', $styleTable, $styleFirstRow);
//        $PHPWord->addTitleStyle(6, $titleStyle);
//        $section->addTextBreak(2);
//        $table = $section->addTable('myOwnTableStyle7');
//        $table->addRow(200);
//        $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '疑点与核实'), $titleStyle);
//        $table->addCell(0, $styleCellMerge);
//
//        $table->addRow(50);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '疑点与核实'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);
//        if (!empty($verify_list)) {
//            foreach ($verify_list as $v9) {
//
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '疑点标题:' . $v9['title']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '关联事件:' . $v9['relation']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                foreach ($v9['contents'] as $kk => $vv9) {
//                    $table->addRow(100);
//                    $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '疑点' . ($kk + 1) . ':' . $vv9['contents']), $fontStyle);
//                    $table->addCell(0, $styleCellMerge);
//                }
//            }
//        }

        //----------疑点与核实 end-----------------

        //----------事件 start-----------------
//        $even_list = mod_event::get_events($id);
//        $PHPWord->addTableStyle('myOwnTableStyle8', $styleTable, $styleFirstRow);
//        $PHPWord->addTitleStyle(6, $titleStyle);
//        $section->addTextBreak(2);
//        $table = $section->addTable('myOwnTableStyle8');
//        $table->addRow(200);
//        $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '事件'), $titleStyle);
//        $table->addCell(0, $styleCellMerge);
//
//        $table->addRow(50);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '事件'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);
//        if (!empty($even_list)) {
//            foreach ($even_list as $v10) {
//
//                $table->addRow(100);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '事件类型:' . $v10['type']), $fontStyle);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '事件时间:' . date("Y-m-d", $v10['datetime'])), $fontStyle);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '涉及人员:' . $v10['target']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '涉及内容:' . $v10['content']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
//            }
//        }

        //----------事件 end-----------------

        //----------任务安排 start-----------------
//        $task_mgt_list = mod_explode_word::get_list($id, 'task_mgt', "id,name,create_time,claim");
//
//
//        $PHPWord->addTableStyle('myOwnTableStyle9', $styleTable, $styleFirstRow);
//        $PHPWord->addTitleStyle(6, $titleStyle);
//        $section->addTextBreak(2);
//        $table = $section->addTable('myOwnTableStyle9');
//        $table->addRow(200);
//        $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '任务安排'), $titleStyle);
//        $table->addCell(0, $styleCellMerge);
//
//        $table->addRow(50);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '任务安排'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);
//
//        if (!empty($task_mgt_list)) {
//            foreach ($task_mgt_list as $v11) {
//
//                $table->addRow(100);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '任务标题:' . $v11['name']), $fontStyle);
//                $table->addCell(4500, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '任务时间:' . date("Y-m-d", $v11['create_time'])), $fontStyle);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '任务要求:' . $v11['claim']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
//                foreach ($v11['content'] as $kk => $vv11) {
//                    $table->addRow(100);
//                    $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '任务目标' . ($kk + 1) . ':' . $vv11['content']), $fontStyle);
//                    $table->addCell(0, $styleCellMerge);
//                }
//
//            }
//        }

        //----------任务安排 end-----------------

        //todo 未知需求
        $section->addTextBreak(2);

        $section->addText(iconv('utf-8', 'GB2312//IGNORE', '办案单位下步动态'), $fontTitle2);
        //证据库start----------

        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "1.最新情况"), $fontTitle3);

        $section->addTextBreak(1);

        $section->addText(iconv('utf-8', 'GB2312//IGNORE', "2.下步部署"), $fontTitle3);

//        //----------官方行动-----------------
        $official_actions_list = mod_official_action::get_official_actions($id);

        $section->addTextBreak(2);

        $section->addText(iconv('utf-8', 'GB2312//IGNORE', '出国信息'), $fontTitle2);


//
//        $PHPWord->addTableStyle('myOwnTableStyle10', $styleTable, $styleFirstRow);
//        $PHPWord->addTitleStyle(6, $titleStyle);
//        $section->addTextBreak(2);
//        $table = $section->addTable('myOwnTableStyle10');
//        $table->addRow(200);
//        $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '官方行动'), $titleStyle);
//        $table->addCell(0, $styleCellMerge);
//
//        $table->addRow(50);
//        $table->addCell(9000, $styleCellCospan)->addText(iconv('utf-8', 'GB2312//IGNORE', '官方行动'), $fontTitle2);
//        $table->addCell(0, $styleCellMerge);
//
        if (!empty($official_actions_list)) {
            foreach ($official_actions_list as $v12) {
                $section->addText(iconv('utf-8', 'GB2312//IGNORE', "详细内容"), $fontTitle3);

                $section->addText(iconv('utf-8', 'GB2312//IGNORE', $v12['remarks']), $fontTitle3);

//
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '计划时间:' . $v12['date']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '行动类型:' . $v12['type']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '行动对象:' . $v12['target']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '办案人员:' . $v12['handle_case']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '目的地:' . $v12['address']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//                $table->addRow(100);
//                $table->addCell(9000, $styleCellCospan2)->addText(iconv('utf-8', 'GB2312//IGNORE', '详细内容:' . $v12['remarks']), $fontStyle);
//                $table->addCell(0, $styleCellMerge);
//
            }
        }

        $section->addTextBreak(2);

//        //----------官方行动 end-----------------


        $footer = $section->createFooter();     //页脚
        $objWriter = \PHPWord_IOFactory::createWriter($PHPWord, 'Word2007');
        $objWriter->save($this->tmp_path . $docname);

        //}
        $filename = $this->tmp_path . $docname;
        header("Content-Type: application/force-download");
        header("Content-Disposition: attachment; filename=" . basename($filename));
        readfile($filename);
        //下载完立即删除（保护数据）
        unlink($filename);
    }

    //修改危机状态的ajax
    public function ajax_edit_case_status()
    {
        $array = array();
        $id = req::item('id');
        $handle_status = req::item('handle_status');
        if ($this->handle_status[$handle_status] == '关注中') {
            $array['handle_status'] = $handle_status;
            $array['case_status_time'] = KALI_TIMESTAMP;
        } else {
            $array['handle_status'] = $handle_status;
            $array['case_status_time'] = 0;
        }
        $result = db::update($this->table)->set($array)->where('id', $id)->execute();
        header("Content-type: application/json; charset=utf-8");
        if ($result) {
            echo json_encode(array('status' => '1'));
        } else {

            echo json_encode(array('status' => '-1'));
        }
    }

    /**
     * 新增实例，进入Page1，仅有实例名称文本框，输入实例名称，
     * 焦点移出后展示与该名称匹配度较高的实例名称，
     * 允许用户open in new window查看实例详情，
     * 确定不是在类似案件名中同案件，再继续进入Page2:添加实例，并赋值Page1写入的实例名称。
     **/
    public function add_step1()
    {
        if (!empty(req::item('name', ''))) {
            $gourl = "?ct=case&ac=add&name=" . req::item('name', '');
            cls_msgbox::show('系统提示', '正在跳转到下一步...', $gourl, 3000);

        }

        tpl::display('case.add_step1.tpl');
    }

    //这是个纯洁的ajax分词查询
    public function ajax_return_case()
    {
        $str = req::item('keyword', '');
        $str = rtrim($str, '案');  //实际的数据每条后面都会有个案字，去掉检索会可能会更准确
        $where[] = array("name", "like", "%$str%");
        $y = 0;
        if (!empty($str)) {
            $ok_str = cls_analysis::instance()
                ->set_source($str)
                ->exec();
            $ok_arr = explode(' ', $ok_str . ' ' . $str);
            $html = '<div class="col-sm-6">';
            $new_data = array();
            if (!empty($ok_arr)) {
                foreach ($ok_arr as $k => $v) {
                    //默认搜索条件
                    //$where[] = array('status','!=','-2');
                    //echo $v."<br />";
                    if (!empty($v)) {
                        //怎样检索才准呢？
                        $where[] = array("name", "like", "%$v%");
                        $rows = db::select("id,name")
                            ->from($this->table)
                            ->where($where)
                            ->as_row()
                            ->execute();
                    }
                    if(!empty($rows))
                    {
                        $y+=1;
                    }

                    if (!empty($y)) {
                        $html .= '<div class="col-sm-9 col-sm-offset-3"><p class="form-control-static clearfix">';
                        $html .= str_replace($v, "<span class='text-danger'>{$v}</span>", $rows['name']);
                        $html .= '<a target="_blank" class="pull-right btn btn-primary btn-outline btn-xs" href=?ct=case&ac=detail&case_id=$rows[id]>查看</a></p></div>';
                    }
                }
                if (empty($y)) {
                    //$html .= '<label class="col-sm-3 control-label"></label>';
                    $html .= '<div class="col-sm-9 col-sm-offset-3"><p class="form-control-static">';
                    $html .= '<span class="text-success">检索不到相类似实例，点击"下一步"添加新的实例</span>';
                    $html .= '</p></div>';
                }
            }
            echo $html . '</div>';

        }
    }

    /**
     * @desc step2
     * */
    public function add()
    {
        if (!empty(req::$posts)) {
            $unsure_id = req::item('unsure_id', ''); //未确定信息传递过来的锅
            $check_data = req::$posts;
            $name = req::item('name');
            $unit = req::item('unit');
            $object = req::item('object');
            $tasknums = req::item('tasknums');
            //$reg_object  = req::item('reg_object'); //注册对象
            $filing_unit = req::item('filing_unit');
            $source_option = req::item('source_option'); //来源单位
            //$info_data = $this -> check($check_data);
            $msg = mod_case::check($check_data);
            if (!empty($msg)) {
                cls_msgbox::show('系统提示', $msg, '-1');
                exit;
            }
            $info_data = $check_data;
            $info_data['rmb'] = !empty($info_data['rmb']) ? $info_data['rmb'] : 0;  //新增涉案金额-rmb
            $info_data['dollar'] = !empty($info_data['dollar']) ? $info_data['dollar'] : 0; //新增涉案金额-美元
            $info_data['is_doubtful'] = !empty($info_data['is_doubtful']) ? $info_data['is_doubtful'] : 0;  //可疑实例
            $info_data['object'] = !empty($info_data['object']) ? $info_data['object'] : '';  //2019-01-19针对对象换为文本框
            if (!empty($info_data['is_doubtful']) && empty($info_data['case_handle_man'])) {

                cls_msgbox::show('系统提示', "疑似实例必须要有实例处理人", '-1');
            }
            $info_data['unit'] = !empty($info_data['unit']) ? $info_data['unit'] : '';
            //新增督办单位处理一下
            if (!empty($info_data['oversee_unit'])) {
                $info_data['oversee_unit'] = implode('、', array_unique($info_data['oversee_unit']));
            }
            if (empty($name)) {
                cls_msgbox::show('系统提示', "实例名称为必填", '-1');
                exit();
            }

            if (!is_array($info_data)) {
                cls_msgbox::show('系统提示', $info_data, '-1');
                exit();
            }

            //新增任务号插件处理 2018-01-12改
            if (!empty($tasknums)) {

                //兼容之前的数据处理
                $tasknums = explode(",", $tasknums);
                foreach ($tasknums as $num) {
                    if (!preg_match('/^[0-9a-zA-Z_、]+$/', $num)) {
                        cls_msgbox::show('系统提示', '危机任务号只能是大小写字母加数字及下划线', '-1');
                        exit();
                    }
                }
                $info_data['tasknum'] = $tasknum = implode('、', array_unique($tasknums));

            } else {
                $info_data['tasknum'] = '';
            }

            //删除调用用后的数组
            unset($info_data['tasknums']);
            //新增项目号插件处理 2018-04-18改
            if (!empty($info_data['project_num'])) {
                //兼容之前的数据处理
                $info_data['project_num'] = explode(",", $info_data['project_num']);
                $info_data['project_num'] = implode('、', array_unique($info_data['project_num']));
            }

            //案件分类改为多选的处理
            if (!empty($info_data['casetype'])) {
                $info_data['casetype'] = implode('、', array_unique($info_data['casetype']));
            }
            //实例处理人

            if (!empty($info_data['case_handle_man'])) {
                $info_data['case_handle_man'] = implode('、', array_unique($info_data['case_handle_man']));
            }

//            //新增危机状态
//            if ($this->handle_status[$info_data['handle_status']] == '关注中') {
//                //datemh字段为测试字段测试完删除
//                $info_data['follow_date'] = !empty($info_data['follow_date']) ? $info_data['follow_date'] : '0000-00-00';
//            }
            //立案地区为立案单位的附属属性
            if (!empty($info_data['filing_unit'])) {
                $filing_unit_info = db::select("address,province,city,area")->from('#PB#_host_unit')->where('id', $info_data['filing_unit'])->as_row()->execute();
                if (!empty($filing_unit_info['address'])) {
                    $info_data['address'] = $filing_unit_info['address'];
                } else {
                    $info_data['province'] = !empty($filing_unit_info['province']) ? $filing_unit_info['province'] : 0;

                    $info_data['city'] = !empty($filing_unit_info['city']) ? $filing_unit_info['city'] : 0;

                    $info_data['area'] = !empty($filing_unit_info['area']) ? $filing_unit_info['area'] : 0;
                }
            }
            $info_data['file_status'] = !empty($info_data['file_status']) ? $info_data['file_status'] : 0;
            $info_data['oversee_status'] = !empty($info_data['oversee_status']) ? $info_data['oversee_status'] : 0;
            $info_data['unit'] = !empty($info_data['unit']) ? $info_data['unit'] : 0;
            $info_data['date'] = !empty(req::item('date'))?strtotime(req::item('date')):0;  //立案时间改为时间戳存储
            $info_data['create_user'] = $this->userinfo['uid'];
            $info_data['create_time'] = KALI_TIMESTAMP;
            $info_data['sys_num'] = $sys_num = date("Ymd") . util::random('5', 'int');
            $info_data['manage_nature'] = !empty($info_data['manage_nature']) ? $info_data['manage_nature'] : 0; //实例相关性

            //来源单位的处理
            if ($source_option == '1') {
                $info_data['source_unit'] = !empty($info_data['customer_list']) ? $info_data['customer_list'] : '';  //客户列表
            } elseif ($source_option == '2') {
                $info_data['source_unit'] = $info_data['host_unit_mine']; //我方单位

            } elseif ($source_option == '3') {
                $info_data['source_unit'] = $info_data['sh_host_unit']; //社会单位

            } elseif ($source_option == '4') {
                $host_unit_unreg_rows = db::select("name")->from("#PB#_host_unit_unreg")->where("name", "=", $info_data['host_unit_unreg'])->as_row()->execute();
                if (!empty($host_unit_unreg_rows)) {
                    cls_msgbox::show('系统提示', "非注册单位已存在，请查询后再录入", -1);
                }
                $host_unit_unreg = array();
                //需插入非注册表
                $host_unit_unreg['name'] = $info_data['host_unit_unreg'];
                $host_unit_unreg["create_time"] = KALI_TIMESTAMP;
                $host_unit_unreg["update_time"] = KALI_TIMESTAMP;
                $host_unit_unreg["create_user"] = kali::$auth->user['uid'];
                $host_unit_unreg["update_user"] = kali::$auth->user['uid'];
                $info_data['source_unit'] = $info_data['host_unit_unreg']; //非注册单位时的来源单位
            }
            $info_data['source_option'] = !empty($source_option) ? $source_option : '';

            if (empty($info_data['source_unit'])) {
                cls_msgbox::show('系统提示', "来源单位不能为空", -1);
            }
            //$r_data = db::get_one("Select * From `#PB#_case` Where `sys_num`='{$sys_num}'");
            $r_data = db::select($this->file_all)->from('#PB#_case')->where('sys_num', $sys_num)->as_row()->execute();
            if (is_array($r_data)) {
                $info_data['sys_num'] = $sys_num = date("Ymd") . util::random('5', 'int');
            }
            //信息来源
//            $sourcus_data = array();
//            $sourcus_data["create_time"] = KALI_TIMESTAMP;
//            $sourcus_data["create_user"] = kali::$auth->user['uid'];
//            $sourcus_data["sourcus"] = $info_data['source_unit'];
//            $sourcus_data["sourcus_type"] = $info_data['source_type'];
//            $sourcus_data["source_option"] = $info_data['source_option'];
//            $sourcus_data["remark"] = req::item("source_remark");
            //数据过滤
            $info_data['info'] = !empty($info_data['info']) ? $info_data['info'] : '';
            $info_data['source_remark'] = !empty($info_data['source_remark']) ? $info_data['source_remark'] : '';
            $info_data['number'] = !empty($info_data['number']) ? $info_data['number'] : '';
            $unit_unreg = $info_data['host_unit_unreg'];
            unset($info_data['host_unit_mine']);
            unset($info_data['host_unit_unreg']);
            unset($info_data['sh_host_unit']);
            //unset($info_data['source_option']);
            unset($info_data['customer_list']);
            unset($info_data['vid']);
            $vid = req::item('vid', '');
            //$v_list
            //插入预警表
            db::start();
            unset($info_data['unsure_id'], $info_data["gourl"], $info_data["csrf_token_name"]);
            list($insert_id, $rows_affected) = db::insert('#PB#_case')->set($info_data)->execute();
            if (!empty($info_data['unit'])) {
                //主办单位统计数+1
                db::query("update `#PB#_host_unit` set `case_count`=case_count+1 where `id`='{$info_data['unit']}'")->execute();
                mod_related_personnel::set_person($info_data['unit'], $insert_id); //社会单位联系人到相关人员
            }
            if (!empty($info_data['filing_unit'])) {
                mod_related_personnel::set_person($info_data['filing_unit'], $insert_id); //社会单位联系人到相关人员
            }
            if (!empty($info_data['source_unit']) && $info_data['source_option'] == '3') {
                mod_related_personnel::set_person($info_data['source_unit'], $insert_id); //社会单位联系人到相关人员
            }
            //echo $vid;
            //插入涉案信息
            //获取涉案信息列表
            if (!empty($vid)) {
                //echo $vid;
                $vids = explode(',', $vid);
                $v_list = db::select($this->vfield)->from(pub_mod_table::TARGET_VIGILANT)->where('id', 'in', $vids)->execute();
                //$this->vfield = "id,content,target_id,type,member_id,infor_type,company_id,object_id"; //预警库检索字段
                foreach ($v_list as $k => $v) {
                    //$object_info = mod_common::return_project_val($val['target_id']);
                    $val['id'] = util::random('web'); //插入涉案表的id
                    $val['case_id'] = $insert_id;
                    $val['vid'] = $v['id'];
                    $val['create_time'] = KALI_TIMESTAMP; //创建时间
                    $val['infor_type'] = $v['infor_type'];
                    $val['content'] = $v['content'];
                    $val['company_id'] = $v['company_id'];
                    $val['member_id'] = $v['member_id'];
                    $val['case_object'] = $info_data['object'];
                    $val['project_id'] = !empty($v['project_id']) ? $v['project_id'] : '';
                    $val['appid'] = !empty($v['appid']) ? $v['appid'] : '';
                    $val['infor_table_id'] = !empty($v['infor_table_id']) ? $v['infor_table_id'] : '';
                    unset($val['target_id']);
                    unset($val['object_id']);
                    db::insert(pub_mod_table::INVOLVED_LOG)->set($val)->execute();
                    //echo 'assssas';

                }
            }
            //$sourcus_data["case_id"] = $insert_id;
            $host_unit_unreg['case_id'] = $insert_id;
            $reg_object_info['case_id'] = $insert_id;  //非注册对象
            //非注册单为空不插入注册单位表
            if (!empty($unit_unreg)) {
                //$host_unit_unreg_id = db::insert('#PB#_host_unit_unreg',$host_unit_unreg);
                list($host_unit_unreg_id, $rows_affected) = db::insert('#PB#_host_unit_unreg')->set($host_unit_unreg)->execute();
                if ($host_unit_unreg_id <= 0) {
                    db::rollback();
                    cls_msgbox::show('系统提示', "非注册单位添加失败", -1);
                }
            }

//            //非注册对象的操作 非注册对象于2019-01-19弃用
//            if (!empty($info_data['not_reg_object'])) {
//                list($reg_object_info_id, $rows_affected) = db::insert('#PB#_target_object_unreg')->set($reg_object_info)->execute();
//                if ($reg_object_info_id <= 0) {
//                    db::rollback();
//                    cls_msgbox::show('系统提示', "非注册对象添加失败", -1);
//                }
//            } else {
//                $info_data['not_reg_object'] = '';
//            }
            //信息来源任意全为空则不加入来源表的操作
//            if (!empty($info_data['source_unit']) || !empty($info_data['source_type'])) {
//                list($sourcus_id, $rows_affected) = db::insert('#PB#_sourcus')->set($sourcus_data)->execute();
//                if ($sourcus_id <= 0) {
//                    db::rollback();
//                    cls_msgbox::show('系统提示', "信息来源添加失败", -1);
//                }
//            }
            if ($unsure_id) {
                $sql = "update `#PB#_unsure` set `become_case`='1',`case_id`='{$insert_id}' where `id`='{$unsure_id}'"; //更新未确定信息状态为已生成实例绑定case_id
                $res = db::query($sql)->execute();
                if ($res <= 0) {
                    db::rollback();
                    cls_msgbox::show('系统提示', "更新未确定信息状态失败", -1);
                }
            }
            db::commit();
            kali::$auth->save_admin_log("实例添加 {$insert_id}");
            $gourl = !empty($unsure_id) ? "?ct=unsure&ac=index" : req::item('gourl', '?ct=case&ac=add_step1');
            cls_msgbox::show('系统提示', "添加成功", $gourl);
        } else {
            $name = req::item('name', '');
            $unsure_id = req::item('unsure_id', '');
            $unsure_info = array();
            if (!empty($unsure_id)) {
                $unsure_info = db::select("id,title,reg_object,object,not_reg_object,suspend_hostunit,source_unittype,source_unit,source_type,info")
                    ->from("#PB#_unsure")->where("id", "=", $unsure_id)->as_row()->execute();
                if ($unsure_info["source_unittype"] == "4") {

                    $unsure_info["source_unit_name"] = $unsure_info['source_unit'];//db::select("id, name")->from('#PB#_host_unit_unreg')->where('id', '=', $unsure_info["source_unit"])->as_row()->execute();
                } elseif($unsure_info["source_unittype"] == "1"){
                    $unsure_info["source_unit_name"] = $unsure_info['source_unit'];
                }elseif($unsure_info["source_unittype"] == "2")
                {
                    //我方单位
                    $unsure_info["source_unit_name"] =  $this->host_unit_mine[$unsure_info['source_unit']];
                }elseif($unsure_info["source_unittype"] == "3")
                {
                    //社会单位
                    $unsure_info["source_unit_name"] = $this->host_unit[$unsure_info['source_unit']];
                }
            }

            if(!empty($unsure_info['object']))
            {
                //检索预警库数据
                $unsure_info['inv_log'] = db::select($this->vfield)
                    ->from(pub_mod_table::TARGET_VIGILANT)
                    ->where('content', 'like', "%$unsure_info[object]%")
                    ->and_where("is_pro",0)
                    ->execute();
//                echo "<pre />";
//                print_r($unsure_info['inv_log']);
                $vids = '';
                if (!empty($unsure_info['inv_log'])) {
                    foreach ($unsure_info['inv_log'] as $v) {
                        $vids .= $v['id'] . ',';
                    }
                }
                $vids = trim($vids, ',');
                tpl::assign('vids',$vids);
            }
            $name = !empty($unsure_info['title']) ? $unsure_info['title'] : $name;
            $date = date("Y-m-d");
            $gourl = '?ct=case&ac=add';
            $timestamp = KALI_TIMESTAMP;
            $token = md5('unique_salt' . $timestamp);
            tpl::assign('timestamp', $timestamp);
            tpl::assign('unsure_info', $unsure_info);
            tpl::assign('token', $token);
            tpl::assign('name', $name);
            tpl::assign('date', $date);
            tpl::assign('gourl', $gourl);
            tpl::display('case.add.tpl');
        }
    }

    //实例临时编辑处理
    public function edit_tmp()
    {
        $id = req::item("id", 0);
        if (!empty(req::$posts)) {
            //实际表
            $info = db::select($this->file_all)->from('#PB#_case')->where('id', $id)->as_row()->execute();
            if (empty($info)) {
                cls_msgbox::show('系统提示', "操作错误", "?ct=case&ac=index");
            }
            $info_data = req::$posts;

            $name = req::item('name');
            $unit = req::item('unit');
            $object = req::item('object');
            $rmb = req::item('rmb', '');
            $dollar = req::item('dollar', '');
            $msg = mod_case::check_edit($info_data);
            //$info_data = $check_data;

            $info_data['rmb'] = !empty($rmb) ? $rmb : 0; //涉案金额rmb(2019-01-14)新增
            $info_data['dollar'] = !empty($dollar) ? $dollar : 0; //涉案金额dollar
            $info_data['is_doubtful'] = !empty($info_data['is_doubtful']) ? $info_data['is_doubtful'] : 0;  //可疑实例
            $info_data['unit'] = !empty($info_data['unit']) ? $info_data['unit'] : '';
            $info_data['date'] = strtotime(req::item('date'));  //立案时间改为时间戳存储
            //编辑不会改变 但是在其他审核的情况会存在的状态
            $info_data['id'] = $info['id'];
            $info_data['status'] = $info['status'];
            $info_data['file_status'] = !empty($info_data['file_status']) ?$info_data['file_status']: 0;
            $info_data['oversee_status'] = !empty($info_data['oversee_status']) ?$info_data['oversee_status']: 0;
            $info_data['unit'] = !empty($info_data['unit']) ?$info_data['unit']: 0;
            $info_data['date'] = !empty($info_data['date']) ?$info_data['date']: 0;
            $info_data['source_option'] = !empty($info_data['source_option'])?$info_data['source_option']: 0;
            $info_data['is_zd_object'] = $info['is_zd_object'];
            $info_data['is_relativity'] = $info['is_relativity'];
            $info_data['is_realness'] = $info['is_realness'];
            $info_data['is_doubtful'] = $info_data['is_doubtful'];  //是否疑似实例
            //$info_data['reg_object']    = isset($info_data['reg_object']) ? $info_data['reg_object'] : '';
            $info_data['object'] = !empty($info_data['object']) ? $info_data['object'] : '';

            $vid = req::item('vid');
            $vids = explode(',', $vid);
            //针对对象不一致
            if ($info['object'] != $info_data['object']) {
                //获取涉案信息列表
                if (!empty($vid)) {
                    $v_list = db::select($this->vfield)->from(pub_mod_table::TARGET_VIGILANT)->where('id', 'in', $vids)->execute();
                    //写入临时表等待审核处理
                    foreach ($v_list as $k => $v) {
                        //$object_info = mod_common::return_project_val($val['target_id']);
                        $val['id'] = util::random('web'); //插入涉案表的id
                        $val['case_id'] = $id;
                        $val['vid'] = $v['id'];
                        $val['infor_table_id'] = $v['infor_table_id'];
                        $val['infor_type'] = $v['infor_type'];
                        $val['content'] = $v['content'];
                        $val['company_id'] = $v['company_id'];
                        $val['member_id'] = $v['member_id'];
                        $val['project_id'] = !empty($v['project_id']) ? $v['project_id'] : '';
                        $val['appid'] = !empty($v['appid']) ? $v['appid'] : '';
                        $val['case_object'] = !empty($info_data['object']) ? $info_data['object'] : '';
                        unset($val['target_id']);
                        unset($val['object_id']);
                        db::insert(pub_mod_table::INVOLVED_LOG_TMP)->set($val)->execute();
                    }
                }
            }else{
                //针对对象改为空
                if(empty($info['object']))
                {
                    //删除涉案表所有数据
                    $res = db::delete(pub_mod_table::INVOLVED_LOG)
                        ->where('case_id', '=', $id)
                        ->execute();
                }else{
                    //不为空，但是选择的条数改变了,删除这个实例下面的所有涉案信息
                    $res = db::delete(pub_mod_table::INVOLVED_LOG_TMP)
                        ->where('case_id', '=', $id)
                        ->execute();
                    if(!empty($vids))
                    {
                        foreach ($vids as $v)
                        {
                            //获得预警表原始数据
                            $rows_v = db::select("
                                    id,infor_table_id,infor_type,content,company_id,member_id,project_id,
                                    appid
                                    ")
                                    ->from(pub_mod_table::TARGET_VIGILANT)
                                    ->where("id",$v)
                                    ->as_row()
                                    ->execute();
                            $val['id'] = util::random('web'); //插入涉案表的id
                            $val['case_id'] = $id;
                            $val['vid'] = $rows_v['id'];
                            $val['infor_table_id'] = $rows_v['infor_table_id'];
                            $val['infor_type'] = !empty($rows_v['infor_type'])?$rows_v['infor_type']:0;
                            $val['content'] = $rows_v['content'];
                            $val['company_id'] = $rows_v['company_id'];
                            $val['member_id'] = $rows_v['member_id'];
                            $val['project_id'] = !empty($rows_v['project_id']) ? $rows_v['project_id'] : '';
                            $val['appid'] = !empty($rows_v['appid']) ? $rows_v['appid'] : '';
                            $val['case_object'] = !empty($info_data['object']) ? $info_data['object'] : '';
                            unset($val['target_id']);
                            unset($val['object_id']);
                            db::insert(pub_mod_table::INVOLVED_LOG_TMP)->set($val)->execute();
                        }
                    }

                }
            }
            $info_data['source_option'] = !empty($info_data['source_option']) ? $info_data['source_option'] : ''; //来源方式删除
            if ($info_data['source_option'] == 1) {

                $info_data['source_unit'] = !empty($info_data['member_user']) ? $info_data['member_user'] : '';       //来源方式删除

            } elseif ($info_data['source_option'] == 2) {

                $info_data['source_unit'] = !empty($info_data['host_unit_mine']) ? $info_data['host_unit_mine'] : '';       //来源方式删除

            } elseif ($info_data['source_option'] == 3) {

                $info_data['source_unit'] = !empty($info_data['sh_host_unit']) ? $info_data['sh_host_unit'] : '';       //来源方式删除

            } elseif ($info_data['source_option'] == 4) {
                $info_data['source_unit'] = !empty($info_data['host_unit_unreg']) ? $info_data['host_unit_unreg'] : '';       //来源方式删除
            }

            //新增督办单位处理一下
            if (!empty($info_data['oversee_unit'])) {
                $info_data['oversee_unit'] = implode('、', array_unique($info_data['oversee_unit']));
            }
//            if ($info_data['reg_object'] == '2') {
//                $info_data['object'] = '';
//                $info_data['manage_nature'] = 0;  //相关性也清空
//            } elseif ($info_data['reg_object'] == '1') {
//                //清空非注册对象
//                $info_data['not_reg_object'] = '';
//                $object_mn = db::select('mn_id')->from('#PB#_target_object')->where('id', $info_data['object'])->as_row()->execute();
//                //赋值为案件相关性,严格判断相关性基础数据存在时才插入，不然相关性列表会有可能插入错误数据
//                if (!empty($object_mn) && array_key_exists($object_mn['mn_id'], $this->manage_nature)) {
//                    $info_data['manage_nature'] = $object_mn['mn_id'];
//                } else {
//                    //相关性要清空
//                    $info_data['manage_nature'] = 0;
//                }
//            } else {
//                $info_data['not_reg_object'] = '';
//                $info_data['object'] = 0;
//                $info_data['manage_nature'] = 0;
//            }
//            //任务号合并到同一个页面的处理(任务号暂屏蔽)
//            $tasknums = req::item('tasknums');
//            $info_data['tasknum'] = '';
//            if (!empty($tasknums)) {
//
//                //兼容之前的数据处理
//                $tasknums = explode(",", $tasknums);
//
//                foreach ($tasknums as $num) {
//                    if (!preg_match('/^[0-9a-zA-Z_、]+$/', $num)) {
//                        cls_msgbox::show('系统提示', '危机任务号只能是大小写字母加数字及下划线', '-1');
//                        exit();
//                    }
//                }
//
//                $info_data['tasknum'] = $tasknum = implode('、', array_unique($tasknums));
//
//            }
            //案例处理人
            $case_handle_man_arr = array();
            if (!empty($info_data['case_handle_man'])) {
                $case_handle_man_arr = $info_data['case_handle_man'];
                $info_data['case_handle_man'] = implode('、', array_unique($info_data['case_handle_man']));
            } else {
                $info_data['case_handle_man'] = '';
            }
            //案件分类改为多选的处理
            if (!empty($info_data['casetype'])) {
                $info_data['casetype'] = implode('、', array_unique($info_data['casetype']));
            } else {
                $info_data['casetype'] = '0';
            }
            //项目号
            $project_num = req::item('project_num', '');
            if (!empty($project_num)) {
                //兼容之前的数据处理
                $project_num = explode(",", $project_num);

                foreach ($project_num as $num) {
                    if (!preg_match('/^[0-9a-zA-Z\_\-、]+$/', $num)) {
                        cls_msgbox::show('系统提示', '项目号只能是大小写字母加数字及下划线及中划线', '-1');
                        exit();
                    }
                }
                $info_data['project_num'] = implode('、', array_unique($project_num));

            } else {
                $info_data['project_num'] = '';
            }

            //立案地区为立案单位的附属属性
            if (!empty($info_data['filing_unit'])) {
                $filing_unit_info = db::select("address,province,city,area")->from('#PB#_host_unit')->where('id', $info_data['filing_unit'])->as_row()->execute();

                if (!empty($filing_unit_info['address'])) {
                    $info_data['address'] = $filing_unit_info['address'];
                    $info_data['province'] = 0;
                    $info_data['city'] = 0;
                    $info_data['area'] = 0;
                } else {
                    $info_data['province'] = !empty($filing_unit_info['province']) ? $filing_unit_info['province'] : 0;
                    $info_data['city'] = !empty($filing_unit_info['city']) ? $filing_unit_info['city'] : 0;
                    $info_data['area'] = !empty($filing_unit_info['area']) ? $filing_unit_info['area'] : 0;
                    $info_data['address'] = '';

                }
            }
            //如果reject存在要重置一下状态
            if (!empty($info_data['reject'])) {
                $info_data[$info_data['reject']] = 0;
            }
            $info_data['create_user'] = $info['create_user'];
            $info_data['create_time'] = $info['create_time'];
            $info_data['update_user'] = $this->userinfo['uid'];
            $info_data['update_time'] = KALI_TIMESTAMP;

            $typeback = req::item('typeback');
            //注销不需要入库的数组
            unset($info_data['host_unit_mine']);
            unset($info_data['vid']);
            unset($info_data['host_unit_unreg']);
            unset($info_data['sh_host_unit']);
            unset($info_data['member_user']);
            unset($info_data['gourl']);
            unset($info_data['typeback']);
            unset($info_data['tasknums']);
            unset($info_data['csrf_token_name']);

            $tmp_data = db::select("id")->from("#PB#_case_edit_tmp")->where("id", $info['id'])->as_row()->execute();
            db::start();
            if (empty($tmp_data)) {
                //不存在的数据就插入一条
                list($insert_id, $rows_affected) = db::insert('#PB#_case_edit_tmp')->set($info_data)->execute();
            } else {
                //还存在的数据的就修改临时表的数据
                db::update('#PB#_case_edit_tmp')->set($info_data)->where('id', $info['id'])->execute();
            }
            //修改编辑状态为审核中的状态
            db::update('#PB#_case')
                ->set(
                    array(
                        "is_edit_pass" => 2,
                        "update_user"=>$this->userinfo['uid'],
                        "update_time"=>KALI_TIMESTAMP)
                )
                ->where('id', $id)
                ->execute();
            db::commit();
            kali::$auth->save_admin_log("实例修改了ID为：{$id}的实例");
            $gourl = '?ct=case&ac=detail&case_id=' . $id . "&forback=index&typeback=$typeback";
            cls_msgbox::show('系统提示', "修改成功,请等待审核", $gourl);
        } else {
            $data = db::select($this->file_all)->from('#PB#_case')->where('id', $id)->as_row()->execute();
            //超级管理员不用理会权限问题
            if ($this->userinfo['groups'] != '1') {
                if ($data['create_user'] != $this->userinfo['uid']) {
                    cls_msgbox::show('系统提示', "您好，你不是提交人，你无权编辑该实例", -1);
                }
            }
            $t_data = str_replace('、', ',', $data['tasknum']);  //任务号
            $p_data = '';
            if (!empty($data['project_num'])) {
                $p_data = str_replace('、', ',', $data['project_num']);  //项目号
            }

            $object_infos = db::select("id,name")->from('#PB#_target_object')->where('id', $data["object"])->as_row()->execute();
            $data["object_name"] = '';
            if (!empty($object_infos)) {
                $data["object_name"] = $object_infos["name"];
            }
            //涉案信息列表
            $involved_log = db::select("id,vid,content")
                ->from(pub_mod_table::INVOLVED_LOG)
                ->where("case_id", $id)
                ->and_where("is_push", 1)
                ->and_where("content", "like", "%$data[object]%")
                ->execute();
            $vids = '';
            if (!empty($involved_log)) {
                foreach ($involved_log as $v) {
                    $vids .= $v['vid'] . ',';
                }
            }

            $vids = trim($vids, ',');
            $data['name'] = trim($data['name']);
            $gourl = '?ct=case&ac=index';
            $date = date("Y-m-d");
            $timestamp = KALI_TIMESTAMP;
            $typeback = req::item('typeback');
            //多选后的实例处理人
            $case_handle_man = explode('、', $data['case_handle_man']);
            //督办单位
            $data['oversee_unit'] = !empty($data['oversee_unit']) ? explode("、", $data['oversee_unit']) : '';
            tpl::assign('case_handle_man', $case_handle_man);
            //多选后的实例编辑类型
            $case_type_arr = explode('、', $data['casetype']);
            tpl::assign('case_type_arr', $case_type_arr);
            $reject = req::item('reject');            //被驳回存在的逻辑
            tpl::assign('vids', $vids);
            tpl::assign('reject', $reject);
            tpl::assign('t_data', $t_data);
            tpl::assign('p_data', $p_data); //项目号
            tpl::assign('typeback', $typeback);
            tpl::assign('backurl', 'javascript:history.back(-1);');
            $token = md5('unique_salt' . $timestamp);
            tpl::assign('timestamp', $timestamp);
            tpl::assign('token', $token);
            tpl::assign('gourl', $gourl);
            tpl::assign('data', $data);
            tpl::assign('date', $date);
            tpl::display('case.edit.tpl');

        }
    }

    /**
     * 实例编辑审核列表
     */
    public function case_edit_tmp_list()
    {
        $casetype = req::item('casetype', 0, 'int');
        $status = req::item('status', 0, 'int');
        //根据这个状态显示不同的详情内容 为空则是审核 my_status_adopt 是审核中的查看 my_status_reject驳回的查看
        $create_user = req::item('create_user');
        $search_type = req::item('search_type');
        $name = req::item('name');
        $update_user = req::item('update_user'); //修改人
        $back_url = mod_util::uri_string();
        setcookie("back_url", $back_url);
        $search_types = array("1" => "实例名称","主办单位", "3" => "修改人");
        //默认搜索
        $where = array(
            array("id", "!=", "")
        );
        if(!empty($update_user))
        {
            $tb_where = array(
                //array('isdeleted', '=', 0),
                array('username', 'like', "%{$update_user}%"),
            );
            $arr = db::select()->from('#PB#_admin')
                ->where($tb_where)
                ->execute();
            if (!empty($arr)) {
                $ids = array_column($arr, 'uid'); //合并一维数组
                $where[] = array('update_user', 'in', $ids);
            }
        }
        //我提交的
        if (!empty($create_user) && $create_user == 'my' && $status == '2') {
            $where[] = array("create_user", "=", $this->userinfo['uid']);
            $where[] = array("is_edit_pass", "=", 2);
        }
        //审核中的
        if ($status == '2' && empty($create_user)) {
            $where[] = array("is_edit_pass", "=", 2);
        }
        //已驳回的
        if (!empty($create_user) && $create_user == 'my' && $status == '-1') {
            $where[] = array("create_user", "=", $this->userinfo['uid']);
            $where[] = array("is_edit_pass", "=", -1);
        }

        if (!empty($casetype)) {
            $where[] = array("casetype", "like", "%$casetype%");
        }

        if (!empty($name)) {
            switch ($search_type) {
                case '1':
                    //$or_where = $name;
                    $where[] = array('name','like',"%{$name}%");

                    break;
                case '2':
                    $tb_where = array(
                        array('isdeleted', '=', 0),
                        array('name', 'like', "%{$name}%"),
                    );
                    $arr = db::select()->from('#PB#_host_unit')
                        ->where($tb_where)
                        ->execute();
                    if (!empty($arr)) {
                        $ids = array_column($arr, 'id'); //合并一维数组
                        $where[] = array('unit', 'in', $ids);
                    }else{
                        $where[] = array('unit', 'in', [-1]);
                    }
                    break;
                case '3':
                    $tb_where = array(
                        //array('isdeleted', '=', 0),
                        array('username', 'like', "%{$name}%"),
                    );
                    $arr = db::select()->from('#PB#_admin')
                        ->where($tb_where)
                        ->execute();
                    if (!empty($arr)) {
                        $ids = array_column($arr, 'uid'); //合并一维数组
                        $where[] = array('update_user', 'in', $ids);
                    }else{
                        $where[] = array('update_user', 'in', [-1]);
                    }
                    break;
            }
        }

        $order_by = 'id';
        $sort = 'desc';
        $row = db::select('count(*) AS `count`')
            ->from($this->table)
            ->where($where)
            ->as_row()
            ->execute();
        $pages = cls_page::make($row['count'], 10); 
        $list = db::select($this->file_all)->from($this->table)
            ->where($where)
            ->order_by($order_by, $sort)
            ->limit($pages['page_size'])
            ->offset($pages['offset'])
            ->execute();

        $edit_status = req::item('edit_status');
        tpl::assign('edit_status', $edit_status);
        tpl::assign('search_types', $search_types);
        tpl::assign('search_type', $search_type);
        tpl::assign('casetype', $casetype);
        tpl::assign('create_user', $create_user);
        tpl::assign('status', $status);
        tpl::assign('list', $list);
        tpl::assign('pages', $pages['show']);
        tpl::display("case_edit_tmp_list.tpl");
    }

    /**
     * 编辑审核详情展示
     **/
    public function case_edit_tmp_detail()
    {
        if (req::$posts) {
            $audit = req::$posts['audit'];
            //审核通过，执行修改
            if ($audit == '1') {
                $res = $this->_edit(req::$posts['case_id']);
                if ($res) {
                    $flow_data = array(
                        'check_time' => KALI_TIMESTAMP,
                        'check_user' => $this->userinfo['uid'],
                        'tc_table' => 'case',  //对应case表
                        'tid' => req::$posts['case_id'],
                        'audit_type' => '0',  //审核类型 1规范性  0其他审核
                        'reject_type' => 'is_edit_pass',   //驳回类型
                        'audit_sta' => '1',  //审核状态
                        'why' => req::$posts['remark']
                    );
                    $insert_id = mod_flow_rule::flow_save($flow_data);  //记录日志
                    //删除临时表数据

                    kali::$auth->save_admin_log("通过了实例id为" . req::$posts['case_id'] . "的修改");
                    cls_msgbox::show('系统提示', '审核已通过，实例已修改！', '?ct=case&ac=case_edit_tmp_list&status=2');
                }
            } elseif ($audit == '-1') {
                $flow_data = array(
                    'check_time' => KALI_TIMESTAMP,
                    'check_user' => $this->userinfo['uid'],
                    'tc_table' => 'case',  //对应case表
                    'tid' => req::$posts['case_id'],
                    'audit_type' => '0',  //审核类型 1规范性  0其他审核
                    'reject_type' => 'is_edit_pass',   //审核的字段名称
                    'audit_sta' => '-1',  //审核状态
                    'why' => req::$posts['why']
                );
                $insert_id = mod_flow_rule::flow_save($flow_data);
                kali::$auth->save_admin_log("驳回实例id为" . req::$posts['case_id'] . "的修改");
                //修改主表审核状态的驳回
                $res = db::update($this->table)->set(array("is_edit_pass" => "-1"))->where('id', req::$posts['case_id'])->execute();
                if ($res) {
                    cls_msgbox::show('系统提示', '审核已驳回，请通知修改人在已驳回列表查看原因！', '?ct=case&ac=case_edit_tmp_list&status=-1&&create_user=my');
                }
            }
        }
        $id = req::item('case_id', 0, 'int');
        $case_status_time = req::item('case_status_time', '');
        $secc = req::item('secc', ''); //审核页面进来的不检测权限
        $warm = req::item('warm', '');
        //原始实例详情
        $data = db::select($this->file_all)->from("#PB#_case")->where('id', $id)->as_row()->execute();

        if (empty($data)) {
            cls_msgbox::show('系统提示', '数据不存在！', '-1');
            exit();
        }
        //临时实例详情
        $data_tmp = db::select($this->file_all)->from("#PB#_case_edit_tmp")->where('id', $id)->as_row()->execute();
//        echo "<pre >";
//        print_r($data);
//        print_r($data_tmp);
        //超级管理员不做任何权限限制
        //        if ($this->userinfo['groups'] != 1) {
        //            $msg = mod_cases_authorizer::confirm_request($id);
        //            //非超级管理员要验证与我提交的不受授权权限控制
        //            if ($this->userinfo['groups'] != 1 && $this->userinfo['uid'] != $data['create_user'] && $secc != '1') {
        //                if ($msg['status'] != true) {
        //                    cls_msgbox::show('系统提示', $msg['document'], $msg['url']);
        //                }
        //            }
        //        }
        //源对象匹配信息
        $old_inv_list = self::_inv_list_log(pub_mod_table::INVOLVED_LOG, $data);

        //临时对象匹配信息
        $new_inv_list = self::_inv_list_log_tmp(pub_mod_table::INVOLVED_LOG_TMP, $data_tmp);
        //判断两个匹配信息是否一样
        $diff_arr = serialize($old_inv_list)!=serialize($new_inv_list)?true:false;
        $data['create_time'] = date('Y-m-d', $data['create_time']);
        $timestamp = KALI_TIMESTAMP;
        $token = md5('unique_salt' . $timestamp);
        $typeback = req::item('typeback', '');
        $forback = req::item('forback', '');  //控制返回页面
        if (empty($typeback)) {
            $backurl = $forback == 'index' ? '?ct=case&ac=index' : 'javascript:history.back(-1)';
        } else {
            $backurl = $typeback == 'about' ? '?ct=case&ac=about_case' : 'javascript:history.back(-1)';
        }
        $unit_info = db::select("province,city")->from("#PB#_host_unit")->where("id", $data['unit'])->as_row()->execute();
        $data['util_province'] = $unit_info['province'];
        $data['util_city'] = $unit_info['city'];
        //驳回原因
        $flow_info = mod_flow_rule::flow_get_data(0, $id, 'is_edit_pass', '', 1);
        $data['oversee_unit'] = !empty($data['oversee_unit']) ? explode("、", $data['oversee_unit']) : '';
        $data_tmp['oversee_unit'] = !empty($data_tmp['oversee_unit']) ? explode("、", $data_tmp['oversee_unit']) : '';
        $edit_status = req::item('edit_status');
        tpl::assign('diff_arr', $diff_arr);
        tpl::assign('edit_status', $edit_status);
        tpl::assign('old_inv_list', $old_inv_list);
        tpl::assign('new_inv_list', $new_inv_list);
        tpl::assign('flow_info', $flow_info);
        tpl::assign('typeback', $typeback);
        tpl::assign('warm', $warm);
        tpl::assign('area', $this->area);  //地区列表
        tpl::assign('groups', $this->userinfo['groups']);
        tpl::assign('user_id', $this->userinfo['uid']);
        tpl::assign('case_status_time', $case_status_time);
        tpl::assign('backurl', $backurl);
        tpl::assign('timestamp', $timestamp);
        tpl::assign('token', $token);
        tpl::assign('ct', req::item('ct'));
        tpl::assign('data', $data);
        tpl::assign('data_tmp', $data_tmp);
        tpl::display("case.edit_tmp.detail.tpl");
    }


    /**
     * @desc 实际的编辑方法
     *
     */
    public function _edit($id)
    {

        $info = db::select($this->file_all)->from($this->tmp_table)->where('id', $id)->as_row()->execute(); //查询临时表数据是否存在该实例
        $case_info = db::select($this->file_all)->from($this->table)->where('id', $id)->as_row()->execute(); //未修改时的真实表数据
        if (empty($info)) {
            cls_msgbox::show('系统提示', "本条数据不存在，请查询后再执行");
        }

        if ($info['object'] != $case_info['object']) {
            //查找源数据表信息
            $inv_log = db::select("id,sta,infor_table_id")
                ->from(pub_mod_table::INVOLVED_LOG)
                ->where('case_id', $case_info['id'])
                ->and_where("content", "like", "%$case_info[object]%")->execute();
            if (empty($info['object'])) {

                //写入日志，不推送之前的信息
                foreach ($inv_log as $v) {
                    $array['type'] = '2';
                    $array['do_time'] = KALI_TIMESTAMP;
                    $array['do_ip'] = req::ip();
                    $array['username'] = $this->userinfo['username'];
                    $array['sta'] = 4;//$v['sta'];
                    $array['ivo_id'] = $v['id'];
                    $array['is_tmp'] = 1; //无效数据的标示
                    $array['msg'] = '因针对对象变更，此涉案已无效';
                    mod_common::save_case_log($array); //写入日志
                    //取消推送，设置为无效的针对对象 sta=>5针对对象已失效
                    db::update(pub_mod_table::INVOLVED_LOG)->set(['is_push' => 0, 'is_tmp' => 1, 'sta' => 4])->where('id', $v['id'])->execute();
                }

            } else {
                //写入日志，不推送之前的信息
                if (!empty($inv_log)) {
                    foreach ($inv_log as $v) {
                        $array['type'] = '2';
                        $array['do_time'] = KALI_TIMESTAMP;
                        $array['do_ip'] = req::ip();
                        $array['username'] = $this->userinfo['username'];
                        $array['sta'] = 4;//$v['sta'];
                        $array['is_tmp'] = 1;//$v['sta'];
                        $array['ivo_id'] = $v['id'];
                        $array['msg'] = '因针对对象变更，此涉案已无效';
                        mod_common::save_case_log($array);
                        db::update(pub_mod_table::INVOLVED_LOG)->set(['is_push' => 0, 'is_tmp' => 1, 'sta' => 4])->where('id', $v['id'])->execute();
                    }
                }
                //获取临时表信息
                $inv_log_tmp = db::select("
                    id,project_id,appid,type,content,company_id,member_id,case_object,
                    case_id,create_user,create_time,update_user,update_time,delete_user,
                    delete_time,update_member,sta,vid,client_status,is_push,infor_type,sid,is_tmp,infor_table_id
                    ")->from(pub_mod_table::INVOLVED_LOG_TMP)
                    ->where('case_id', $id)
                    ->and_where("content", "like", "%$info[object]%")
                    ->and_where("is_tmp", "=", 0)
                    ->execute();
//                echo "<pre />";
//                print_r($inv_log_tmp);
//                exit;
                //插入正式表
                if (!empty($inv_log_tmp)) {
                    foreach ($inv_log_tmp as $v) {
                        $new_arr['id'] = util::random('web');
                        $new_arr['project_id'] = $v['project_id'];
                        $new_arr['appid'] = $v['appid'];
                        $new_arr['content'] = $v['content'];
                        $new_arr['case_object'] = $v['case_object'];
                        $new_arr['company_id'] = $v['company_id'];
                        $new_arr['member_id'] = $v['member_id'];
                        $new_arr['case_id'] = $v['case_id'];
                        $new_arr['create_user'] = $v['create_user'];
                        $new_arr['create_time'] = KALI_TIMESTAMP;
                        $new_arr['update_user'] = $v['update_user'];
                        $new_arr['update_time'] = $v['update_time'];
                        $new_arr['delete_user'] = $v['delete_user'];
                        $new_arr['delete_time'] = $v['delete_time'];
                        $new_arr['update_member'] = $v['update_member'];
                        $new_arr['sta'] = $v['sta'];
                        $new_arr['vid'] = $v['vid'];
                        $new_arr['client_status'] = $v['client_status'];
                        $new_arr['is_push'] = 1;
                        $new_arr['push_time'] = KALI_TIMESTAMP;
                        $new_arr['infor_type'] = $v['infor_type'];
                        $new_arr['sid'] = $v['sid'];
                        $new_arr['infor_table_id'] = $v['infor_table_id'];
                        $new_arr['is_tmp'] = 0;
                        //写入日志
                        $array['type'] = '2';
                        $array['do_time'] = KALI_TIMESTAMP;
                        $array['do_ip'] = req::ip();
                        $array['username'] = $this->userinfo['username'];
                        $array['sta'] = 0;//$v['sta'];
                        $array['ivo_id'] = $new_arr['id'];
                        $array['msg'] = '等待确认';
                        mod_common::save_case_log($array);
                        list($insert_id, $rows_affected) = db::insert(pub_mod_table::INVOLVED_LOG)->set($new_arr)->execute();

                    }

                }
                //删除临时表信息，防止下次匹配多次显示重复内容
                db::delete(pub_mod_table::INVOLVED_LOG_TMP)->where("case_id", "=", $id)->execute();

            }
        }else{
            //获取临时表信息
            $inv_log_tmp = db::select("
                    id,project_id,appid,type,content,company_id,member_id,case_object,
                    case_id,create_user,create_time,update_user,update_time,delete_user,
                    delete_time,update_member,sta,vid,client_status,is_push,infor_type,sid,is_tmp,infor_table_id
                    ")->from(pub_mod_table::INVOLVED_LOG_TMP)
                ->where('case_id', $id)
                ->and_where("content", "like", "%$info[object]%")
                ->and_where("is_tmp", "=", 0)
                ->execute();
            //获取正式表插入的信息
            $inv_log = db::select("
                    id,project_id,appid,type,content,company_id,member_id,case_object,
                    case_id,create_user,create_time,update_user,update_time,delete_user,
                    delete_time,update_member,sta,vid,client_status,is_push,infor_type,sid,is_tmp,infor_table_id
                    ")->from(pub_mod_table::INVOLVED_LOG)
                ->where('case_id', $id)
                //->and_where("content", "like", "%$info[object]%")
                //->and_where("is_tmp", "=", 1) //正式的都是已推送的
                ->execute();
            $inv_ids = [];
            if(!empty($inv_log))
            {
                foreach($inv_log as $v)
                {
                    $inv_ids = $v['id'];
                }
            }
            //把原来的废弃

            //判断两条数据是否一样
            if(serialize($inv_log_tmp)!=serialize($inv_log))
            {
                //插入正式表
                if (!empty($inv_log_tmp)) {
                    foreach ($inv_log_tmp as $v) {
                        $new_arr['id'] = util::random('web');
                        $new_arr['project_id'] = $v['project_id'];
                        $new_arr['appid'] = $v['appid'];
                        $new_arr['content'] = $v['content'];
                        $new_arr['case_object'] = $v['case_object'];
                        $new_arr['company_id'] = $v['company_id'];
                        $new_arr['member_id'] = $v['member_id'];
                        $new_arr['case_id'] = $v['case_id'];
                        $new_arr['create_user'] = $v['create_user'];
                        $new_arr['create_time'] = KALI_TIMESTAMP;
                        $new_arr['update_user'] = $v['update_user'];
                        $new_arr['update_time'] = $v['update_time'];
                        $new_arr['delete_user'] = $v['delete_user'];
                        $new_arr['delete_time'] = $v['delete_time'];
                        $new_arr['update_member'] = $v['update_member'];
                        $new_arr['sta'] = $v['sta'];
                        $new_arr['vid'] = $v['vid'];
                        $new_arr['client_status'] = $v['client_status'];
                        $new_arr['is_push'] = 1;
                        $new_arr['push_time'] = KALI_TIMESTAMP;
                        $new_arr['infor_type'] = $v['infor_type'];
                        $new_arr['sid'] = $v['sid'];
                        $new_arr['infor_table_id'] = $v['infor_table_id'];
                        $new_arr['is_tmp'] = 0;
                        //写入日志
                        $array['type'] = '2';
                        $array['do_time'] = KALI_TIMESTAMP;
                        $array['do_ip'] = req::ip();
                        $array['username'] = $this->userinfo['username'];
                        $array['sta'] = 0;//$v['sta'];
                        $array['ivo_id'] = $new_arr['id'];
                        $array['msg'] = '等待确认';
                        mod_common::save_case_log($array);
                        list($insert_id, $rows_affected) = db::insert(pub_mod_table::INVOLVED_LOG)->set($new_arr)->execute();

                    }

                }
            }
        }
        //只修改需要修改的字段，不然在其他审核中会修改掉一些状态
        $info_data = array(
            'name' => $info['name'],
            'number' => $info['number'],
            'info' => $info['info'],
            'unit' => $info['unit'],
            'object_info' => $info['object_info'], //针对对象判定依据
            'case_info' => $info['case_info'],
            'unit_type' => $info['unit_type'],
            'file_status' => !empty($info['file_status']) ? $info['file_status'] : 0,
            'reg_object' => $info['reg_object'],
            'object' => $info['object'],
            'oversee_status' => !empty($info['oversee_status'])?$info['oversee_status']:0,
            'oversee_unit' => $info['oversee_unit'],  //新增督办单位
            'casetype' => $info['casetype'],
            'case_handle_man' => $info['case_handle_man'],
            'manage_nature' => !empty($info['manage_nature'])?$info['manage_nature']:0,  //对象相关性
            'source_option' => $info['source_option'],  //来源单位类型
            'source_unit' => $info['source_unit'],      //来源单位
            'source_remark' => $info['source_remark'],  //来源备注
            'sys_num' => $info['sys_num'],
            'rmb' => $info['rmb'],
            'dollar' => $info['dollar'],
            'source_type' => !empty($info['source_type'])?$info['source_type']:0,
            'create_user' => $info['create_user'],
//            'create_time' => $info['create_time'],
            'update_user' => $info['update_user'],
            'update_time' => $info['update_time'],
            //'case_nature' =>info['case_nature'], 实例性质
            'project_num' => $info['project_num'],
            'privacy_level' => $info['privacy_level'],
            'filing_unit' => $info['filing_unit'],
            'not_reg_object' => $info['not_reg_object'],
            'case_unit_type' => !empty($info['case_unit_type'])?$info['case_unit_type']:0,
            'is_doubtful' => $info['is_doubtful'],
            'is_edit_pass' => 0,    //重置一下修改状态可无限编辑提交修改
            'is_realness' => $info['is_realness'],
            'case_des' => $info['case_des'],
        );


        //主办单位case_count+1 -1 的操作
        if ($case_info['unit'] != $info['unit']) {
            db::query("update `#PB#_host_unit` set `case_count`=case_count-1 where `id`='{$info['unit']}' ")->execute();
            db::query("update `#PB#_host_unit` set `case_count`=case_count+1 where `id`='{$info_data['unit']}' ")->execute();

        }
//        $info_data['reg_object'] = isset($info_data['reg_object']) ? $info_data['reg_object'] : '';
//        if ($info_data['reg_object'] == '2') {
//            $info_data['object'] = 0;
//            $info_data['manage_nature'] = 0;  //相关性也清空
//            $reg_object_info['name'] = $info_data['not_reg_object'];
//            $reg_object_info['case_id'] = $id; //绑定实例id
//            $reg_object_info["create_time"] = KALI_TIMESTAMP;
//            $reg_object_info["create_user"] = $info_data['create_user'];
//            //这里还需插入非注册对象列表
//        } elseif ($info_data['reg_object'] == '1') {
//            //清空非注册对象
//            $info_data['not_reg_object'] = '';
//            $object_mn = db::select('mn_id')->from('#PB#_target_object')->where('id', $info_data['object'])->as_row()->execute();
//            //赋值为案件相关性,严格判断相关性基础数据存在时才插入，不然相关性列表会有可能插入错误数据
//            if (!empty($object_mn) && array_key_exists($object_mn['mn_id'], $this->manage_nature)) {
//                $info_data['manage_nature'] = $object_mn['mn_id'];
//            } else {
//                //相关性要清空
//                $info_data['manage_nature'] = 0;
//            }
//        } else {
//            $info_data['not_reg_object'] = '';
//            $info_data['object'] = 0;
//            $info_data['manage_nature'] = 0;
//        }

        //立案地区为立案单位的附属属性
        if (!empty($info_data['filing_unit'])) {
            $filing_unit_info = db::select("address,province,city,area")->from('#PB#_host_unit')->where('id', $info_data['filing_unit'])->as_row()->execute();
            if (!empty($filing_unit_info['address'])) {
                $info_data['address'] = $filing_unit_info['address'];
                $info_data['province'] = 0;
                $info_data['city'] = 0;
                $info_data['area'] = 0;
            } else {
                $info_data['province'] = !empty($filing_unit_info['province']) ? $filing_unit_info['province'] : 0;
                $info_data['city'] = !empty($filing_unit_info['city']) ? $filing_unit_info['city'] : 0;
                $info_data['area'] = !empty($filing_unit_info['area']) ? $filing_unit_info['area'] : 0;
                $info_data['address'] = '';

            }
        }
        //如果reject存在要重置一下状态
        if (!empty($info_data['reject'])) {
            $info_data[$info_data['reject']] = 0;
        }

        //$typeback = req::item('typeback');
        //注销不需要入库的数组
        unset($info_data['host_unit_mine']);
        unset($info_data['host_unit_unreg']);
        unset($info_data['sh_host_unit']);
        //unset($info_data['source_option']);
        unset($info_data['gourl']);
        unset($info_data['typeback']);
        unset($info_data['tasknums']);
        unset($info_data['case_id']);
        //真实性通过的情况才会执行
//        if ($info_data['status'] == '1') {
//            //查询关联的协查信息表
//            pub_mod_warning::save_mix($info_data);
//        }
        //不可疑实例编辑为可疑实例的时候要重置一下实例审核状态
        if ($case_info['is_doubtful'] != $info_data['is_doubtful'] && $info_data['is_doubtful'] == '1') {
            $info_data['confirm_doubtful'] = 0;
        }
        db::start();
        //非注册对象的操作
//        if (!empty($info_data['not_reg_object'])) {
//            //先删除之前的再插入现在的
//            $update_set['isdeleted'] = '1';
//            //echo $info_data['not_reg_object'];
//            db::update('#PB#_target_object_unreg')->set($update_set)->where('case_id', $info['id'])->execute();
//            list($reg_object_info_id, $rows_affected) = db::insert('#PB#_target_object_unreg')->set($reg_object_info)->execute();
//            if ($reg_object_info_id <= 0) {
//                db::rollback();
//                cls_msgbox::show('系统提示', "非注册对象添加失败", -1);
//            }
//        }
        if (!empty($info_data['unit'])) {
            mod_related_personnel::set_person($info_data['unit'], $id); //社会单位联系人到相关人员
        }
        if (!empty($info_data['filing_unit'])) {
            mod_related_personnel::set_person($info_data['filing_unit'], $id); //社会单位联系人到相关人员
        }
        db::update('#PB#_case')->set($info_data)->where('id', $id)->execute();
        db::delete($this->tmp_table)->where('id', $id)->execute();          //删除临时表数据
        //修改了这个案件要通知实例处理人
        if (!empty($case_handle_man_arr)) {
            //获取案件名称
            $case_name = mod_case::get_case_name($id);
            foreach ($case_handle_man_arr as $v) {
                cls_potato::send(mod_case::get_potato($v), $case_name, 'message');
            }
        }
        db::commit();
        return true;

    }

    /**
     *
     * @desc 删除
     */
    public function del()
    {
        $id = req::item('id', 0);
        if (empty($id)) {
            cls_msgbox::show('系统提示', "删除失败，请选择要删除的内容", '-1');
        }
        // $data = db::get_one("Select * From `#PB#_case` Where `id`='{$id}'");
        $data = db::select($this->file_all)->from("#PB#_case")->where('id', '=', $id)->execute();
        if (empty($data)) {
            cls_msgbox::show('系统提示', '数据不存在！', '-1');
            exit();
        }

        $rows['update_time'] = KALI_TIMESTAMP;
        $rows['isdeleted'] = 1;
        $rows['update_user'] = kali::$auth->user['uid'];
        //db::update('#PB#_case', req::$forms, "`id`='{$id}'");
        db::update($this->table)->set($rows)->where('id', $id)->execute();
        kali::$auth->save_admin_log("实例删除 {$id}");

        util::shutdown_function(
            ['pub_mod_warning', 'save_mix'],
            [$id, 1]
        );

        $gourl = '?ct=case&ac=index';
        cls_msgbox::show('系统提示', "删除成功", $gourl);
    }




//    /**
//     *
//     * @desc ajax请求 获取针对对象数据
//     */
//    public function get_object()
//    {
//        $key    = req::item("key",'');
//        if($key == '')
//        {
//            exit(json_encode(array('message'=>'','value'=>''))) ;
//        }
//        //模糊查询管理信息名
//        $data  = db::get_all("Select name,id From  `#PB#_target_object` Where `name` like '%{$key}%'  ");
//        exit(json_encode(array('message'=>'','value'=>$data))) ;
//    }
//
//    /**
//     *
//     * @desc ajax请求 获取主办单位数据
//     */
//    public function get_unit()
//    {
//        $key    = req::item("key",'');
//        if($key == '')
//        {
//            exit(json_encode(array('message'=>'','value'=>''))) ;
//        }
//        //模糊查询管理信息名
//        $data  = db::get_all("Select name,id From  `#PB#_host_unit` Where `name` like '%{$key}%'  ");
//
//        exit(json_encode(array('message'=>'','value'=>$data))) ;
//    }

    /**
     * ajax返回涉案匹配列表
     */
    public function ajax_return_involved()
    {
        //通用接口返回格式
        $arr['code'] = 0;
        $arr['msg'] = 'no data';
        $arr['data'] = array();
        //$arr['pages'] = '';
        $keyword = req::item('keyword', '');
        $type = req::item('type', '');
        if (empty($keyword)) {
            $arr['code'] = -1;
            $arr['msg'] = 'keyword can not empty';
            exit(json_encode($arr));
        }
        // $pages = cls_page::make($row['count'], 10);
        if ($type == '1') {
            //检索预警库数据
            $list = db::select('
            cm_target_vigilant.id,cm_target_vigilant.content,cm_target_vigilant.target_id,cm_target_vigilant.type
            ,cm_target_vigilant.member_id,cm_target_vigilant.infor_type,cm_target_vigilant.company_id,
            cm_target_vigilant.object_id,cm_target_vigilant.project_id,cm_target_vigilant.appid,cm_target_vigilant.sid
            ,cm_target_vigilant.create_time,cm_involved_log.sta
        ')
                ->from(pub_mod_table::TARGET_VIGILANT)
                ->join(pub_mod_table::INVOLVED_LOG)
                ->on(pub_mod_table::TARGET_VIGILANT . '.id', "=", pub_mod_table::INVOLVED_LOG . '.vid')
                ->where(pub_mod_table::TARGET_VIGILANT . '.content', 'like', "%$keyword%")
                ->execute();

        } else {
            //检索预警库数据
            $list = db::select($this->vfield)
                ->from(pub_mod_table::TARGET_VIGILANT)
                ->where('content', 'like', "%$keyword%")
                ->execute();
        }
        if (!empty($list)) {
            $arr['code'] = 1;
            $arr['msg'] = 'success';
            foreach ($list as $k => $v) {


                //返回项目 && app
                $new_arr['id'] = $v['id'];
                $new_arr['content'] = $v['content']; //对象名称
                if ($v['infor_type'] != 2) {
                    $new_arr['project_name'] = array_key_exists($v['project_id'], $this->object) ? $this->object[$v['project_id']] : '';//!empty($project['name'])?$project['name']:'-';
                    $new_arr['app_name'] = array_key_exists($v['appid'], $this->object) ? $this->object[$v['appid']] : '';//!empty($app['name'])?$app['name']:'-';
                } else {
                    $new_arr['project_name'] = self::_get_object_str($v['project_id'], $this->object);//!empty($project['name'])?$project['name']:'-';
                    $new_arr['app_name'] = self::_get_object_str($v['appid'], $this->object);
                }
                $company = db::select("name")->from(pub_mod_table::COMPANY)->where("id", $v['company_id'])->as_row()->execute();
                $member = db::select("nickname")->from(pub_mod_table::MEMBER)->where("uid", $v['member_id'])->as_row()->execute();
                $new_arr['company_name'] = !empty($company['name']) ? $company['name'] : '-';
                $new_arr['member_nickname'] = !empty($member['nickname']) ? $member['nickname'] : '-';  //用户称呼
                $new_arr['create_time'] = !empty($v['create_time']) ? date("Y-m-d H:i", $v['create_time']) : '-'; //创建时间
                if (isset($v['sta'])) {
                    $new_arr['sta'] = array_key_exists($v['sta'], $this->case_sta) ? $this->case_sta[$v['sta']] : null; //状态
                }
                $arr['data'][] = $new_arr;
            }
            // $arr['pages'] = $pages['show'];
        }
        exit(json_encode($arr));

    }

    public static function _get_object_str($object_str, $object)
    {
        $str = '';
        $arr = explode(',', $object_str);
        if (!empty($arr)) {
            foreach ($arr as $v) {
                if (array_key_exists($v, $object)) {
                    $str .= $object[$v] . ',';
                }

            }
        }

        return trim($str, ',');
    }


    /**
     * date 2018-01-04
     * 新增相关性实例
     * author xiaozhe
     */
    public function about_case()
    {
        //获得nav
        $search_types = array("1" => "实例名称", "2" => "实例号", "3" => "针对对象", "4" => "主办单位", "6" => "实例编号");
        $casetype = req::item('casetype', 0, 'int');
        //$nav_data = db::get_all("select `id`,`name` from `#PB#_target_correlation`");
        //$nav_data = db::select("id,type_name")->from("#PB#_target_correlation")->where('isdeleted', '=', 0)->execute();

        $name_order = req::item('name_order', '');
        $casetype_order = req::item('casetype_order', '');
        $status_order = req::item('status_order', '');
        $name = req::item("name");
        $sys_id = req::item("sys_id");    //生成的系统id （100000+自增id）
        $create_user = req::item('create_user'); //我管理的相关实例
        $search_type = req::item("search_type");
        $tj_sdate = req::item("tj_sdate");
        $tj_edate = req::item("tj_edate");
        $la_sdate = req::item("la_sdate");
        $province_s = req::item("province");
        $city = req::item("city");
        $area = req::item("area");
        $manage_nature = req::item('manage_nature', 'null');  //相关性
        //设置返回网址
        $back_url = mod_util::uri_string();
        setcookie("back_url", $back_url);
        //显示所有相关性审核通过的信息
        //$where[] = " c.`isdeleted`='0' and `is_relativity`='1' ".$create_user_str;
        //默认搜索
        $where = array(
            array('isdeleted', '=', 0),
            array('manage_nature', '!=', 0),
        );


        //不是超级管理员再去验证授权权限
//        if ($this->userinfo['groups'] != 1 ) {
//            $authorizer_caseid = mod_cases_authorizer::get_case_ids();
//            if (!empty($authorizer_caseid)) {
//                $where[] = array('id', 'in', $authorizer_caseid);
//            } else {
//                $where[] = array('id', '!=', '-1');
//            }
//        }

        //2018-07-23 新增实例编号查询
        if (!empty($sys_id)) {
            $real_id = ltrim($sys_id, 'C') - 10000;
            $where[] = array("id", "=", $real_id);
        }
        //默认排序
        $order_by = 'id';
        $sort = 'desc';
        $create_user_str = "";
        if (!empty($create_user) && $create_user == 'my') {
            //只能创建者查看
            $where[] = array('create_user', '=', $this->userinfo['uid']);
        }
        if (!empty($name)) {
            switch ($search_type) {
                case '1':
                    //$or_where[] = $name;
                    $where[] = array('name', 'like', "%{$name}%");
                    break;
                case '2':
                    //2018-01-09修复，模糊搜索
                    $where[] = "  `number` Like '%{$name}%'";
                    break;
                case '3':
                    //2018-01-09修复，针对对象不能直接根据关键字搜
                    //$arr = db::get_all("select `id` from `#PB#_target_object` where   `isdeleted`='0' and `name` Like '%{$name}%'  " );
                    $tb_where = array(
                        array('isdeleted', '=', 0),
                        array('name', 'like', "%{$name}%"),
                    );
                    $arr = db::select()->from('#PB#_target_object')
                        ->where($tb_where)
                        ->execute();

                    //如果基础数据没有直接找表内的注册对象
                    if (!empty($arr)) {
                        $ids = array_column($arr, 'id'); //合并一维数组
                        $where[] = array('object', 'in', $ids);
                        $or_where[] = array('not_reg_object', 'like', "%{$name}%");
                    } else {
                        $where[] = array('not_reg_object', 'like', "%{$name}%");
                    }
                    break;
                case '4':
                    //2018-01-09修复，不能直接根据关键字搜
                    $tb_where = array(
                        array('isdeleted', '=', 0),
                        array('name', 'like', "%{$name}%"),
                    );
                    $arr = db::select()->from('#PB#_host_unit')
                        ->where($tb_where)
                        ->execute();
                    if (!empty($arr)) {
                        $ids = array_column($arr, 'id'); //合并一维数组
                        $where[] = array('unit', 'in', $ids);
                    }
                    break;
                case '6':
                    //2018-07-23 新增实例编号查询
                    $real_id = ltrim($name, 'C') - 10000;
                    $where[] = array("id", "=", $real_id);
                    break;
            }
        }


        //相关性
        if (!empty($manage_nature) && $manage_nature != 'null') {
            $where[] = array('manage_nature', '=', $manage_nature);
        }
        //根据地区搜索
        if (!empty($province_s)) {
            $where[] = array('province', '=', $province_s);
            $pid = $province_s;
            $arr = db::select("id,fullname")->from('#PB#_region')
                ->where('pid', $pid)
                ->execute();
            $city_option = "<option value= >请选择</option>";
            if (!empty($arr)) {
                foreach ($arr as $k => $v) {
                    if (!empty($city) && $city == $v['id']) {
                        $city_option .= "<option selected value=$v[id] >$v[fullname]</option>";
                    } else {
                        $city_option .= "<option value=$v[id] >$v[fullname]</option>";
                    }

                }
            }
        }
        $city_option = !empty($city_option) ? $city_option : '<option value="">城市</option>';
        tpl::assign('city_option', $city_option);
        if (!empty($city)) {
            //$where[] = "`city`='{$city}' ";
            $where[] = array('city', '=', $city);
            $pid = $city;
//            $sql  = "select `code`,`fullname` from `#PB#_region` where `parent_code`='{$pid}'";
//            $arr = db::get_all($sql);
            $arr = db::select("id,fullname")->from('#PB#_region')
                ->where('pid', $pid)
                ->execute();
            $area_option = "<option value= >请选择</option>";
            if (!empty($arr)) {
                foreach ($arr as $k => $v) {
                    if (!empty($area) && $area == $v['id']) {
                        $area_option .= "<option selected value=$v[id] >$v[fullname]</option>";
                    } else {
                        $area_option .= "<option value=$v[id] >$v[fullname]</option>";
                    }

                }
            }

        }
        $area_option = !empty($area_option) ? $area_option : '<option value="">区域</option>';
        tpl::assign('area_option', $area_option);
        if (!empty($area)) {
            //$where[] = "  `area`='{$area}' ";
            $where[] = array('area', '=', $area);
        }

        if (!empty($tj_sdate) && !empty($tj_edate)) {
            $tj_stime = strtotime($tj_sdate);
            $tj_etime = strtotime($tj_edate);
            $time_arr = array($tj_stime, $tj_etime);
            //提交时间搜索
            //$where[] = "  `create_time`  between '{$tj_stime}' and '{$tj_etime}'  ";
            $where[] = array('create_time', 'between', $time_arr);
        }

        if (!empty($la_sdate) && !empty($la_edate)) {
            $la_stime = strtotime($la_sdate);
            $la_etime = strtotime($la_edate);
            $time_arr = array($la_stime, $tj_etime);
            //$where[] = "  `date`  between '{$la_sdate}' and '{$la_edate}'  ";
            $where[] = array('date', 'between', $time_arr);
        }
        if (!empty($casetype) && $casetype != '-1') {

            //$where[] = "  `casetype`='{$casetype}'";
            $where[] = array("casetype", "like", "%$casetype%");
        } elseif ($casetype == '-1') {
            $where[] = array("casetype", "=", "0");
        }

        //案例名称排序
        if ($name_order != '') {
            $order_by = 'name';
            $sort = $name_order == 'desc' ? '   desc' : 'asc';
            $name_order = $name_order == 'desc' ? 'asc' : 'desc';
            tpl::assign('name_order', $name_order);
        }
        if ($casetype_order != '') {
            $order_by = 'casetype';
            $sort = $casetype_order == 'desc' ? 'desc' : 'asc';
            $casetype_order = $casetype_order == 'desc' ? 'asc' : 'desc';
            tpl::assign('casetype_order', $casetype_order);
        }
        if ($status_order != '') {
            $order_by = 'status';
            $sort = $status_order == 'desc' ? 'desc' : 'asc';
            $status_order = $status_order == 'desc' ? 'asc' : 'desc';
            tpl::assign('status_order', $status_order);
        }

        if (isset($or_where)) {
            $or_where[] = array('not_reg_object', 'like', "%{$name}%");
            $row = db::select('count(*) AS `count`')
                ->from($this->table)
                ->or_where_open()
                ->where($where)
                ->or_where_close()
                ->or_where($or_where)
                ->as_row()
                ->execute();

            $pages = cls_page::make($row['count'], 10);
            $list = db::select($this->file_all)->from($this->table)
                ->or_where_open()
                ->or_where($or_where)
                ->or_where_close()
                ->where($where)
                ->order_by($order_by, $sort)
                ->limit($pages['page_size'])
                ->offset($pages['offset'])
                ->execute();
        } else {
            $row = db::select('count(*) AS `count`')
                ->from($this->table)
                ->where($where)
                ->as_row()
                ->execute();

            $pages = cls_page::make($row['count'], 10);
            $list = db::select($this->file_all)->from($this->table)
                ->where($where)
                ->order_by($order_by, $sort)
                ->limit($pages['page_size'])
                ->offset($pages['offset'])
                ->execute();
        }

        $unit_ids = array();
        $object_ids = array();
        if ($list) {
            foreach ($list as $key => $value) {
                // $list[$key]['info'] = util::utf8_substr_num($value['info'],50);
                if ($value["unit"]) {
                    $unit_ids[$value["unit"]] = $value["unit"];
                }
                if ($value["object"]) {
                    $object_ids[$value["object"]] = $value["object"];
                }
            }
        }
        tpl::assign('search_type', $search_type);
        $province = db::select("id,fullname")->from('#PB#_region')
            ->where('pid', '0')
            ->execute();
        //获得当前province下的所有city
        if (!empty($data['province'])) {
            $city = db::select("id,fullname")->from('#PB#_region')
                ->where('pid', $data['province'])
                ->execute();
            tpl::assign('city', $city);
        }
        //获得当前所有city下的area
        if (!empty($data['city'])) {
            $area = db::select("id,fullname")->from('#PB#_region')
                ->where('pid', $data['city'])
                ->execute();
            tpl::assign('town', $area);
        }
        tpl::assign('province', $province);
        tpl::assign('province_s', $province_s);
        tpl::assign('city', $city);
        tpl::assign('area', $area);
        tpl::assign('casetype', $casetype);
        //tpl::assign('manage_nature', $manage_nature);
        $typeback = req::item('typeback');
        tpl::assign('search_case', $casetype);
        tpl::assign('search_types', $search_types);
        tpl::assign('create_user', $create_user);

        tpl::assign('typeback', $typeback);
        tpl::assign('name_order', $name_order);
        tpl::assign('casetype_order', $casetype_order);
        tpl::assign('status_order', $status_order);
        tpl::assign('list', $list);
        tpl::assign('pages', $pages['show']);
        tpl::assign('manage_nature', $manage_nature);
        //tpl::assign('nav_data', $nav_data);
        tpl::display('case.about_case.tpl');

    }


    //获取主办单位基础数据
    public function _get_unit()
    {
        $where = array(
            array('isdeleted', '=', 0),
            array('status', '=', 1),
        );
        // $arr = db::get_all("select * from `#PB#_host_unit` where `isdeleted`='0' and `status`='1'");
        $arr = db::select("id,name,parent_path")->from('#PB#_host_unit')->where($where)->execute();
        $unit_arr = mod_host_unit::get_all_name('#PB#_host_unit');
        $data = array();
        if (!empty($arr)) {
            foreach ($arr as $k => $v) {
                $parent_top = explode(',', $v['parent_path']);

                $parent_top_name = mod_host_unit::get_key_value($parent_top[count($parent_top) - 1], $unit_arr);
                if ($parent_top_name) {
                    $data[$v['id']] = $parent_top_name . ' 下属 ' . $v['name'];
                } else {
                    $data[$v['id']] = $v['name'];
                }

            }
        }
        return $data;
    }


    //ajax 获取地区列表
    public function ajax_get_chird_area()
    {
        $pid = req::item('pid');
        $arr = db::select("id,fullname")->from('#PB#_region')->where('pid', $pid)->execute();
        $option = "<option value= >请选择</option>";
        if (!empty($arr)) {
            foreach ($arr as $k => $v) {
                $option .= "<option value=$v[id]>$v[fullname]</option>";
            }
        }
        echo $option;
    }


    /**
     * 实例审核列表
     * date 2018-01-05
     * author xiaozhe
     **/
    public function case_audit()
    {
        $base_url = mod_util::uri_string();
        setcookie('base_url', $base_url);
        setcookie('back_url', $base_url);
        $search_types = array("1" => "实例名称", "4" => "主办单位","5"=>"提交人");
        $casetype = req::item('casetype', 0, 'int');
        $audit = req::item('audit', '');
        $secc = req::item('secc', '');
        $is_audit = 'is_' . $audit;
        if($audit=='status')
        {
            $is_audit='status';
        }
        $is_audit_val = req::item($is_audit, '');
        //$search_open = req::item('search_open',false); //开启表单搜索
        $status = req::item('status');
        $create_user = req::item('create_user');
        $search_type = req::item("search_type");
        //默认搜索
        $where[] = array('isdeleted', '=', 0);
        //默认排序
        $search['orderBy'] = req::item('orderBy','id');
        $search['sort']  = req::item('sort','desc');//子列表排序
        //待审核
//        if ($status == '9' || $status == '1') {
//            //$where[] = array('audit_user_id', '=', $this->userinfo['uid']);
//        }

        if($status==9){
            $where[] = array($is_audit, '=', 9);
        }
        //我提交的
        if (!empty($create_user) && $create_user == 'my') {
            $where[] = array('create_user', '=', $this->userinfo['uid']);
        }
        //待受理（不显示我提交与不显示已受理的）
        if ($status == '0') {
            $where[] = array('create_user', '!=', $this->userinfo['uid']);

            if ($is_audit_val != '0') {
                $where[] = array('status', '=', 0);
            }
        }
        //被驳回或已审核显示所有我提交被驳回/审核的
        if ($status == '-1') {
            //$reject_str = " and `create_user`='{$this->userinfo['uid']}' ";
            //$where[] = array('create_user', '=', $this->userinfo['uid']);
            $where[] = ['status','=',-1];
        }
        $name_order = req::item('name_order', '');
        $casetype_order = req::item('casetype_order', '');
        $status_order = req::item('status_order', '');
        $name = req::item("name");


        //线性审核顺序 规范性->针对性->相关性->真实性
        if (!empty($audit) && $audit == 'status' && $create_user != 'my') {
            //我提交的是没有这个状态条件的，所以需要过滤一下才能搜索出来
            if (!empty($status)) {
                $where[] = array('status', '=', $status);
            }
        } elseif (!empty($audit) && $audit == 'zd_object' && $create_user != 'my') {
            //针对对象审核的列表
            //$where[] = "  `status`='1'   and `$is_audit`='{$status}'".$audit_user_str.$not_me_str.$reject_str;
            $where[] = array('status', '=', 1);
            $where[] = array($is_audit, '=', $status);
            //不要相关性审核了(2019-4-30又加上了相关性审核)
//        elseif(!empty($audit) && $audit=='relativity' && $create_user!='my')
//        {
//            //相关性审核的列表（针对对象通过才能审核相关性）
//            //$where[] = "  `status`='1' and `is_zd_object`='1'   and `$is_audit`='{$status}'".$audit_user_str.$not_me_str.$reject_str;
//
//        $where[]=  array('status','=',1);
//            $where[]=array('is_zd_object','=',1);
//            $where[]=array($is_audit,'=',$status);
//
//        }
        }elseif(!empty($audit) && $audit=='relativity' && $create_user!='my'){
            //新增相关性审核逻辑  规范性审核通过后才能显示相关性
            $where[]=  array('status','=',1);
            //$where[]=array('is_zd_object','=',1);
            //echo $is_audit.'--'.$status;
            $where[]=array($is_audit,'=',$status);
        } elseif (!empty($audit) && $audit == 'zd_object_again' && $create_user != 'my') {
            $where[] = array('status', '=', 1);
            $where[] = array('is_zd_object', '=', 1);  //针对对象初审通过
            $where[] = array($is_audit, '=', $status);
        } elseif (!empty($audit) && $audit == 'realness' && $create_user != 'my') {
            //真实性审核的操作
            if ($status == '-1') {
                //驳回只显示我被驳回的
                $where = array(
                    array('status', '=', 1),
                    array('is_discard', '=', 1),
                    array($is_audit, '=', -1),
                    array('create_user', '=', $this->userinfo['uid']),
                );
            } elseif ($status == '-2') {
                //废弃显示全部废弃的
                //$where[] = "  `status`='1' and `is_discard`='1'  and `$is_audit`='-1' ";
                $where = array(
                    array('status', '=', 1),
                    array('is_discard', '=', 1),
                    array($is_audit, '=', -1),
                );
            } else {
                //$where[] = "  `status`='1' and `is_relativity`='1' and `is_zd_object`='1'  and `$is_audit`='{$status}'".$audit_user_str.$not_me_str.$reject_str;
                $where = array(
                    array('status', '=', 1),
                    array('is_zd_object', '=', 1),
                    array('is_zd_object_again', '=', 1), //复审
                    array($is_audit, '=', $status),
                    //array('audit_user_id','=',$this->userinfo['uid']),
                    //array('create_user','!=',$this->userinfo['uid']),
                    //array('create_user','=',$this->userinfo['uid'])
                );
            }

        }

        if (!empty($casetype) && $casetype != '-1') {

            //$where[] = "  `casetype`='{$casetype}'";
            $where[] = array("casetype", "like", "%$casetype%");
        } elseif ($casetype == '-1') {
            $where[] = array("casetype", "=", "0");
        }


        //案例名称排序
//        if ($name_order != '') {
//            $order_by = 'name';
//            $sort = $name_order == 'desc' ? '   desc' : 'asc';
//            $name_order = $name_order == 'desc' ? 'asc' : 'desc';
//            tpl::assign('name_order', $name_order);
//        }
//        if ($casetype_order != '') {
//            $order_by = 'casetype';
//            $sort = $casetype_order == 'desc' ? 'desc' : 'asc';
//            $casetype_order = $casetype_order == 'desc' ? 'asc' : 'desc';
//            tpl::assign('casetype_order', $casetype_order);
//        }
//        if ($status_order != '') {
//            $order_by = 'status';
//            $sort = $status_order == 'desc' ? 'desc' : 'asc';
//            $status_order = $status_order == 'desc' ? 'asc' : 'desc';
//            tpl::assign('status_order', $status_order);
//        }
        //排序优化


        if (!empty($name)) {
            switch ($search_type) {
                case '1':
                    $or_where = $name;
                    //$where[] = array('name','like',"%{$name}%");

                    break;
                case '4':
                    $tb_where = array(
                        array('isdeleted', '=', 0),
                        array('name', 'like', "%{$name}%"),
                    );
                    $arr = db::select()->from('#PB#_host_unit')
                        ->where($tb_where)
                        ->execute();
                    if (!empty($arr)) {
                        $ids = array_column($arr, 'id'); //合并一维数组
                        $where[] = array('unit', 'in', $ids);
                    }
                    break;
                case '5':
                    $tb_where = array(
                        //array('isdeleted', '=', 0),
                        array('username', 'like', "%{$name}%"),
                    );
                    $arr = db::select()->from('#PB#_admin')
                        ->where($tb_where)
                        ->execute();
                    if (!empty($arr)) {
                        $ids = array_column($arr, 'uid'); //合并一维数组
                        $where[] = array('create_user', 'in', $ids);
                    }
                    break;
            }
        }
        //or条件的增加
        if (isset($or_where)) {
            $or_where = array(
                array('name', 'like', "%$name%"),
            );
            $row = db::select('count(*) AS `count`')
                ->from($this->table)
                ->or_where_open()
                ->where($where)
                ->or_where_close()
                ->or_where($or_where)
                ->as_row()
                ->execute();

            $pages = cls_page::make($row['count'], 10);
            $list = db::select($this->file_all)->from($this->table)
                ->or_where_open()
                ->or_where($or_where)
                ->or_where_close()
                ->where($where)
                ->order_by($search['orderBy'], $search['sort'])
                ->limit($pages['page_size'])
                ->offset($pages['offset'])
                ->execute();

        } else {
            $row = db::select('count(*) AS `count`')
                ->from($this->table)
                ->where($where)
                ->as_row()
                ->execute();

            $pages = cls_page::make($row['count'], 10);
            $list = db::select($this->file_all)->from($this->table)
                ->where($where)
                ->order_by($search['orderBy'], $search['sort'])
                ->limit($pages['page_size'])
                ->offset($pages['offset'])
                ->execute();
        }


        tpl::assign('search_type', $search_type);
        //根据不同状态合并列表行数
        $cosplan = $status == '0' || $status == '9' || $status == '1' ? '8' : '7';
        tpl::assign('ct', 'case');
        tpl::assign('cosplan', $cosplan);
        tpl::assign(' is_audit ', $is_audit);
        tpl::assign('is_audit_val', $is_audit_val);
        tpl::assign('create_user', $create_user);
        tpl::assign('name_order', $name_order);
        tpl::assign('casetype_order', $casetype_order);
        tpl::assign('status_order', $status_order);
        tpl::assign('secc', $secc);
        tpl::assign('search', $search);
        //案例搜索
        tpl::assign('search_case', $casetype);
        tpl::assign('search_types', $search_types);
        tpl::assign('search_type', $search_type);
        tpl::assign('pages', $pages['show']);
        tpl::assign('is_audit', 'is_' . $audit);
        tpl::assign('audit', $audit);
        tpl::assign('status', $status);
        tpl::assign('casetype', $casetype);
        tpl::assign('nav_data', $this->case_audit_nav);
        tpl::assign('list', $list);
        tpl::display('case.case_audit.tpl');
    }

    /**
     * 修改受理状态
     */
    public function case_accept()
    {
        $base_url = mod_util::uri_string();
        setcookie('base_url', $base_url);
        $audit = req::item('filed', '');
        $status = req::item('status', '');
        $id = req::item('case_id', 0, 'int');
        $show = req::item('show', '');
        $data = db::select($this->file_all)->from($this->table)->where('id', $id)->as_row()->execute();
        //记录基本信息的返回按钮
        if (empty($data)) {
            cls_msgbox::show('系统提示', '数据不存在！', '-1');
            exit();
        }
        //$object_infos   = db::get_one("Select `mn_id` From  `#PB#_target_object` Where id='{$data["object"]}' ");
        $object_infos = db::select("mn_id")->from('#PB#_target_object')->where('id', $data["object"])->as_row()->execute();
        //$data["object"] = $object_infos["name"];
        //根据针对对象获取对应相关性
        $data['mn_id'] = $object_infos['mn_id'];

        $data['create_time'] = date('Y-m-d', $data['create_time']);
        $area = ''; //立案地区
        if (!empty($data['province']) && !empty($data['city']) && !empty($data['area'])) {
            $area = $this->area[$data['province']] . '-' . $this->area[$data['city']] . '-' . $this->area[$data['area']];
        } elseif (empty($data['province']) && empty($data['city']) && empty($data['area'])) {
            $area = '';
        } elseif (!empty($data['province']) && empty($data['city']) && empty($data['area'])) {
            $area = $this->area[$data['province']];
        } elseif (!empty($data['province']) && !empty($data['city']) && empty($data['area'])) {
            $area = $this->area[$data['province']] . '-' . $this->area[$data['city']];
        }
        $data['area'] = $area;
        $timestamp = KALI_TIMESTAMP;
        $token = md5('unique_salt' . $timestamp);
        $typeback = req::item('typeback', '');

        $forback = req::item('forback', '');  //控制返回页面
        if (empty($typeback)) {
            $backurl = $forback == 'index' ? '?ct=case&ac=index' : 'javascript:history.back(-1)';
        } else {
            $backurl = $typeback == 'about' ? '?ct=case&ac=about_case' : 'javascript:history.back(-1)';
        }
        $flow_info = array();
        $audit_type = '';
        if ($audit == 'status') {
            $audit_type = '1';
        } else {
            $audit_type = '0';
        }
        //获取审核信息
        //获取审核信息
        if ($show == 'show') {
            $flow_info = mod_flow_rule::flow_get_data($audit_type, $id, $audit, 'all');
            if ($flow_info) {
                foreach ($flow_info as $k => $v) {
                    $flow_info[$k]['check_user'] = mod_case::get_username($v['check_user']);
                }
            }
            //$flow_info['check_user'] = mod_case::get_username($flow_info['check_user']);
        } elseif ($show == 'show_why') {
            $flow_info = mod_flow_rule::flow_get_data($audit_type, $id, $audit, 'all', '1');
            if ($flow_info) {
                foreach ($flow_info as $k => $v) {
                    $flow_info[$k]['check_user'] = !empty(mod_case::get_username($v['check_user'])) ? mod_case::get_username($v['check_user']) : 0;
                }
            }

        }

        $unit_info = db::select("province,city")->from("#PB#_host_unit")->where("id", $data['unit'])->as_row()->execute();
        $data['unit_province'] = $unit_info['province'];
        $data['unit_city'] = $unit_info['city'];
        $data['oversee_unit'] = !empty($data['oversee_unit']) ? explode("、", $data['oversee_unit']) : '';
        $involved_log = self::_inv_list_log(pub_mod_table::INVOLVED_LOG, $data);
        tpl::assign('involved_log', $involved_log);
        tpl::assign('flow_info', $flow_info);
        tpl::assign('audit', $audit);
        tpl::assign('status', $status);
        tpl::assign('show', $show);
        tpl::assign('typeback', $typeback);
        tpl::assign('backurl', $backurl);
        tpl::assign('timestamp', $timestamp);
        tpl::assign('token', $token);
        tpl::assign('ct', req::item('ct'));
        tpl::assign('data', $data);
        tpl::display('case_accept.detail.tpl');


    }


    //审核受理处理
    public function case_accept_action()
    {
        $base_url = mod_util::uri_string();
        setcookie('base_url', $base_url);
        $id = req::item('case_id');
        //$case_name = db::get_one("select `name` from `$this->table` where `id`='{$id}'");
        $case_name = db::select("name")->from($this->table)->where('id', $id)->as_row()->execute();
        $case_name['name'] = !empty($case_name['name']) ? $case_name['name'] : '';
        $filed = req::item('filed');
        if ($filed != 'status') {
            if ($filed == 'confirm_doubtful') {
                $is_filed = "confirm_doubtful";  //可疑受理
            } else {
                $is_filed = 'is_' . $filed;
            }

        } else {
            $is_filed = $filed;
        }
        $status = req::item('status');
        $audit_user_id = $this->userinfo['uid']; //当前审核人ID
        //$sql = "update `$this->table` set `$is_filed`='9',`audit_user_id`='{$audit_user_id}' where `id`='{$id}'";  //9为受理状态
        $update_arr[$is_filed] = '9';
        $update_arr['audit_user_id'] = $audit_user_id;

        //$row = db::get_one("select `id`,`create_user` from `$this->table` where `id`='{$id}'");
        $row = db::select("id,create_user")->from($this->table)->where('id', $id)->as_row()->execute();
        if (empty($row)) {
            cls_msgbox::show('系统提示', 'id不存在', "-1");
        }
        //自己提交的自己无法审核
        if ($row['create_user'] == $this->userinfo['uid']) {
            cls_msgbox::show('系统提示', '不能审核自己提交的实例', "-1");
        }
        db::start();
        $res = db::update($this->table)->set($update_arr)->where('id', $id)->execute();
        if ($res <= 0) {
            db::rollback();
            cls_msgbox::show('系统提示', "任务受理失败", -1);
        }
        db::commit();
        //跳转到对应的审核页面
        if ($is_filed == 'confirm_doubtful') {
            $gourl = "?ct=case&ac=case_doubtful_detail&filed=$filed&case_id=$id&status=9";
        } else {
            $gourl = "?ct=case&ac=audit_case_detail&filed=$filed&case_id=$id&status=9";
        }

        cls_msgbox::show('系统提示', "你成功受理了实例为:" . $case_name['name'] . "的实例", $gourl);
    }

    //取消受理处理
    public function case_accept_return_action()
    {
        $id = req::item('case_id');
        $filed = req::item('filed');


        //区分status状态与其他状态的处理
        if ($filed != 'status') {
            $is_filed = 'is_' . $filed;
        } else {
            $is_filed = $filed;
        }
        if($is_filed == 'is_confirm_doubtful')
        {
            $is_filed='confirm_doubtful'; //取消受理的处理
        }
        $status = req::item('status');
        $audit_user_id = $this->userinfo['uid']; //当前审核人ID
        $sql = "update `$this->table` set `$is_filed`='0',`audit_user_id`='' where `id`='{$id}'";  //0为未受理状态取消后受理人也该为空

        $row = db::select("id,create_user")->from($this->table)->where('id', $id)->as_row()->execute();
        if (empty($row)) {
            cls_msgbox::show('系统提示', 'id不存在', "-1");
        }
        //自己提交的自己无法审核
        if ($row['create_user'] == $this->userinfo['uid']) {
            cls_msgbox::show('系统提示', '你好，不能取消自己提交的实例', "-1");
        }

        db::start();

        $res = db::query($sql)->execute();
        if ($res <= 0) {
            db::rollback();
            cls_msgbox::show('系统提示', "取消受理失败", -1);
        }
        db::commit();
        //跳转到对应的审核
        if($is_filed=='confirm_doubtful')
        {
            $gourl = "?ct=case&ac=case_doubtful&audit=$is_filed&case_id=$id&status=0&$is_filed=0";
        }else{
            $gourl = "?ct=case&ac=case_audit&audit=$filed&case_id=$id&status=0&$is_filed=0";
        }
        cls_msgbox::show('系统提示', "你已经成功取消了该受理的实例", $gourl);
    }

    //处理针对对象的重新提交处理
    public function case_zd_object_reject()
    {
        $id = req::item('case_id');
        $filed = req::item('filed');
        $is_filed = 'is_' . $filed;
        $object = req::item('object');
        $mn_id = db::select("mn_id")->from("#PB#_target_object")->where("id", $object)->as_row()->execute();
        //重新提交后出更新为待受理状态
        $sql = "update `$this->table` set `$is_filed`='0',`reg_object`='1',`object`='{$object}',`audit_user_id`='',`manage_nature`='{$mn_id['mn_id']}' where `id`='{$id}' ";
        $res = db::query($sql)->execute();
        if ($res) {
            kali::$auth->save_admin_log("在被驳回中重新提交了{$id}的实例");
            //跳转到对应的审核
            $gourl = "?ct=case&ac=case_audit&audit=$filed&case_id=$id&create_user=my";
            cls_msgbox::show('系统提示', "你已经成功重新提交了针对对象的审核", $gourl);
        }

    }

    //真实性审核、针对对象审核、相关性审核
    public function audit_case_detail()
    {
        if (req::$posts) {
            $data = req::$posts;
            $data['is_audit'] = !empty($data['is_audit']) ? $data['is_audit'] : '';
            $audit = req::item('audit', 'status');
            if (empty($audit)) $audit = 'status';//设置一个默认值
            $is_audit = 'is_' . $audit;
            $check_sta = req::item($audit);  //审核状态 通过/驳回
            $remark = req::item('remark');
            $why = req::item('why');
            $filed = req::item($audit, '');
            if ($data['status'] == '9') {
                $gosta_url = 0; //跳转到待审核
            } else {
                $gosta_url = $data['status'];
            }
            if ($audit == 'realness') { //真实性

                if ($check_sta == '1') {  //审核通过
                    $sql = "update `#PB#_case` set `$is_audit`='1',`$audit`='1'  where `id`='{$data['id']}'";
                    $msg = '已通过';
                    $flow_data = array(
                        'check_time' => KALI_TIMESTAMP,
                        'check_user' => $this->userinfo['uid'],
                        'tc_table' => 'case',  //对应case表
                        'tid' => $data['id'],
                        'audit_type' => '0',  //审核类型 1规范性  0其他审核
                        'reject_type' => $audit,   //驳回类型
                        'audit_sta' => '1',  //审核状态
                        'why' => $remark
                    );
                    $insert_id = mod_flow_rule::flow_save($flow_data);
                    $info_data = db::select($this->file_all)
                        ->from($this->table)
                        ->where("id", $data['id'])->execute();
                    pub_mod_warning::save_mix($info_data);
                } elseif ($check_sta == '-1') {
                    //真实性不真实状态为2,真实性被驳回即废弃 is_discard=1 为废弃状态
                    $sql = "update `#PB#_case` set `$is_audit`='-1',`is_discard`='1'  where `id`='{$data['id']}'";
                    $msg = '已驳回';
                    $flow_data = array(
                        'check_time' => KALI_TIMESTAMP,
                        'check_user' => $this->userinfo['uid'],
                        'tc_table' => 'case',  //对应case表
                        'tid' => $data['id'],
                        'audit_type' => '0',  //审核类型 1规范性  0其他审核
                        'reject_type' => $audit,   //驳回类型
                        'audit_sta' => '-1',  //审核状态
                        'why' => $why
                    );
                    $insert_id = mod_flow_rule::flow_save($flow_data);

                }
                //$sql = "update `#PB#_case` set `$is_audit`='1',`$audit`='{$filed}' where `id`='{$data['id']}'";
                $res = db::query($sql)->execute();
                if ($res) {
                    cls_msgbox::show('系统提示', $this->case_audit_nav[$audit] . $msg, "?ct=case&ac=case_audit&audit=$audit&status=$gosta_url&secc=1");
                    exit();
                }
            } elseif ($audit == 'zd_object') { //针对对象(客户)
                if ($check_sta == '1') {
                    //审核通过 改变对象状态为通过即可
                    $sql = "update `#PB#_case` set `$is_audit`='1'  where `id`='{$data['id']}'";
                    $msg = '已通过';
                    $flow_data = array(
                        'check_time' => KALI_TIMESTAMP,
                        'check_user' => $this->userinfo['uid'],
                        'tc_table' => 'case',  //对应case表
                        'tid' => $data['id'],
                        'audit_type' => '0',  //审核类型 1规范性  0其他审核
                        'reject_type' => $audit,   //驳回类型
                        'audit_sta' => '1',  //审核状态
                        'why' => $remark
                    );
                    $insert_id = mod_flow_rule::flow_save($flow_data);
                } elseif ($check_sta == '-1') {
                    $sql = "update `#PB#_case` set `$is_audit`='-1' where `id`='{$data['id']}'";
                    $msg = '已驳回';
                    $flow_data = array(
                        'check_time' => KALI_TIMESTAMP,
                        'check_user' => $this->userinfo['uid'],
                        'tc_table' => 'case',  //对应case表
                        'tid' => $data['id'],
                        'audit_type' => '0',  //审核类型 1规范性  0其他审核
                        'reject_type' => $audit,   //驳回类型
                        'audit_sta' => '-1',  //审核状态
                        'why' => $why
                    );
                    $insert_id = mod_flow_rule::flow_save($flow_data);

                }
                $res = db::query($sql)->execute();
                if ($res) {
                    //?ct=case&ac=case_audit&audit=zd_object&status=0&is_zd_object=0&secc=1
                    cls_msgbox::show('系统提示', $this->case_audit_nav[$audit] . $msg, "?ct=case&ac=case_audit&audit=zd_object_again&status=$gosta_url&is_zd_object_again=0secc=1");
                    exit();
                }

            } elseif ($audit == 'relativity') { //相关性
                if ($check_sta == '1') {  //审核通过
                    $manage_nature = req::item('manage_nature');
                    $sql = "update `#PB#_case` set `$is_audit`='1',`manage_nature`='{$manage_nature}'  where `id`='{$data['id']}'";
                    $msg = '已通过';
                    $flow_data = array(
                        'check_time' => KALI_TIMESTAMP,
                        'check_user' => $this->userinfo['uid'],
                        'tc_table' => 'case',  //对应case表
                        'tid' => $data['id'],
                        'audit_type' => '0',  //审核类型 1规范性  0其他审核
                        'reject_type' => $audit,   //驳回类型
                        'audit_sta' => '1',  //审核状态
                        'why' => $remark
                    );
                    $insert_id = mod_flow_rule::flow_save($flow_data);

                } elseif ($check_sta == '-1') {
                    //相关性允许审核时修改
                    $sql = "update `#PB#_case` set `$is_audit`='-1'  where `id`='{$data['id']}'";
                    $msg = '已驳回';
                    $flow_data = array(
                        'check_time' => KALI_TIMESTAMP,
                        'check_user' => $this->userinfo['uid'],
                        'tc_table' => 'case',  //对应case表
                        'tid' => $data['id'],
                        'audit_type' => '0',  //审核类型 1规范性 2 复核 0初审
                        'reject_type' => $audit,   //驳回类型
                        'audit_sta' => '-1',  //审核状态
                        'why' => $why
                    );
                    $insert_id = mod_flow_rule::flow_save($flow_data);
                }
                //获取处理人
                $case_handle_man = db::select("case_handle_man")->from($this->table)->where("id", $data['id'])->as_row()->execute();
                $case_handle_man_arr = !empty($case_handle_man) ? explode("、", $case_handle_man['case_handle_man']) : '';
                if (!empty($case_handle_man_arr)) {
                    //获取案件名称
                    $case_name = mod_case::get_case_name($data['id']);
                    foreach ($case_handle_man_arr as $v) {
                        if (!empty(mod_case::get_potato($v))) {
                            cls_potato::send(mod_case::get_potato($v), $case_name, 'message');
                        }
                    }
                }
                $res = db::query($sql)->execute();
                if ($res) {
                    cls_msgbox::show('系统提示', $this->case_audit_nav[$audit] . $msg, "?ct=case&ac=case_audit&audit=$audit&status=$gosta_url&secc=1");
                    exit();
                }

            } elseif ($audit == 'zd_object_again') {  //针对对象复审
                if ($check_sta == '1') {
                    //审核通过 改变对象状态为通过即可
                    $sql = "update `#PB#_case` set `$is_audit`='1'  where `id`='{$data['id']}'";
                    $msg = '已通过';
                    $flow_data = array(
                        'check_time' => KALI_TIMESTAMP,
                        'check_user' => $this->userinfo['uid'],
                        'tc_table' => 'case',  //对应case表
                        'tid' => $data['id'],
                        'audit_type' => '0',  //审核类型 1规范性  0其他审核
                        'reject_type' => $audit,   //日志类型
                        'audit_sta' => '1',  //审核状态
                        'why' => $remark
                    );
                    $insert_id = mod_flow_rule::flow_save($flow_data);
                } elseif ($check_sta == '-1') {
                    //复审的驳回 重置复审审核状态，回到初审让其重新提交,所以记录的是初审的驳回日志
                    $sql = "update `#PB#_case` set `$is_audit`='0',`is_zd_object`='-1' where `id`='{$data['id']}'";
                    $msg = '已驳回';
                    $flow_data = array(
                        'check_time' => KALI_TIMESTAMP,
                        'check_user' => $this->userinfo['uid'],
                        'tc_table' => 'case',  //对应case表
                        'tid' => $data['id'],
                        'audit_type' => '0',  //审核类型 1规范性  0其他审核
                        'reject_type' => 'zd_object',   //驳回类型
                        'audit_sta' => '-1',  //审核状态
                        'why' => $why
                    );
                    $insert_id = mod_flow_rule::flow_save($flow_data);
                }
                $res = db::query($sql)->execute();
                if ($res) {
                    cls_msgbox::show('系统提示', $this->case_audit_nav[$audit] . $msg, "?ct=case&ac=case_audit&audit=$audit&status=$gosta_url&secc=1");
                    exit();
                }
            } else {
                exit('非法操作');
            }
        } else {
            $id = req::item('case_id', 0, 'int');
            $status = req::item('status', '');
            $audit = req::item('filed', '');
            $data = db::select($this->file_all)->from($this->table)->where('id', $id)->as_row()->execute();
            if (empty($data)) {
                cls_msgbox::show('系统提示', '数据不存在！', '-1');
                exit();
            }
            $object_infos = db::select("mn_id,name")->from('#PB#_target_object')->where('id', $data["object"])->as_row()->execute();
            $data["object_name"] = $object_infos["name"];
            $flow_info = array();
            $audit_type = '';
            if ($audit == 'status') {
                $audit_type = '1';
            } else {
                $audit_type = '0';
            }
            $flow_info = mod_flow_rule::flow_get_data($audit_type, $id, $audit, 'all', '1');
            if ($flow_info) {
                foreach ($flow_info as $k => $vv) {
                    $flow_info[$k]['check_user'] = mod_case::get_username($vv['check_user']);
                }
            }
            //如果是针对对象复核显示一下初审的备注
            if ($audit == 'zd_object_again') {
                //返回针对对象初审备注信息
                $zb_obeject_info = mod_flow_rule::flow_get_data($audit_type, $id, 'zd_object');
                tpl::assign('zb_obeject_info', $zb_obeject_info);

            }
            //涉案信息列表
            $involved_log = db::select("vid,content,project_id,appid,company_id,id,sta,create_time")
                ->from(pub_mod_table::INVOLVED_LOG)
                ->where("case_id", $id)
                ->and_where("content", "like", "%$data[object]%")
                ->execute();
            $new_arr = array();
            $vids = '';
            if (!empty($involved_log)) {
                foreach ($involved_log as $k => $v) {
                    $vids .= $v['vid'] . ',';
                    $project = db::select("name")->from(pub_mod_table::TARGET_OBJECT)->where('id', $v['project_id'])->as_row()->execute();
                    $app = db::select("name")->from(pub_mod_table::TARGET_OBJECT)->where('id', $v['appid'])->as_row()->execute();
                    $company = db::select("name")
                        ->from(pub_mod_table::COMPANY)
                        ->where("id", $v['company_id'])
                        ->as_row()
                        ->execute();
                    $new_arr[$k]['id'] = $v['id'];
                    $new_arr[$k]['sta'] = $v['sta'];
                    $new_arr[$k]['create_time'] = $v['create_time'];
                    $new_arr[$k]['content'] = $v['content']; //对象名称
                    $new_arr[$k]['project_name'] = !empty($project['name']) ? $project['name'] : '-';
                    $new_arr[$k]['app_name'] = !empty($app['name']) ? $app['name'] : '-';
                    $new_arr[$k]['company_name'] = !empty($company['name']) ? $company['name'] : '-';
                }
            }
            $vids = trim($vids, ',');
            $timestamp = KALI_TIMESTAMP;
            $unit_info = db::select("province,city")->from("#PB#_host_unit")->where("id", $data['unit'])->as_row()->execute();
            $data['unit_province'] = $unit_info['province'];
            $data['unit_city'] = $unit_info['city'];
            //线索库上传的json字段
            $data['unreg_arr'] = !empty($data['unreg_field'])?json_decode($data['unreg_field'],true):0;
//            echo "<pre />";
//            print_r($data['unreg_arr']);

            //多选后的实例处理人
            $case_handle_man = explode('、', $data['case_handle_man']);
            //督办单位
            $data['oversee_unit'] = !empty($data['oversee_unit']) ? explode("、", $data['oversee_unit']) : '';
            tpl::assign('case_handle_man', $case_handle_man);
            tpl::assign('status', $status);
            tpl::assign('new_arr', $new_arr);
            //多选后的实例编辑类型
            $case_type_arr = explode('、', $data['casetype']);
            tpl::assign('case_type_arr', $case_type_arr);
            $token = md5('unique_salt' . $timestamp);
            tpl::assign('vids', $vids);
            tpl::assign('flow_info', $flow_info);
            //tpl::assign('province', $province);
            tpl::assign('timestamp', $timestamp);
            tpl::assign('token', $token);
            tpl::assign('ct', req::item('ct'));
            tpl::assign('data', $data);
            tpl::assign('audit', $audit);
            tpl::assign('nav_data', $this->case_audit_nav);
            tpl::display('case.audit_case.detail.tpl');
        }

    }


    //规范性审核的修改
    public function case_audit_edit()
    {
        $is_audit = req::item('is_audit',0);
        $id = req::item('id');
        $row = db::select("status")->from(pub_mod_table::CASE)->where('id',$id)->as_row()->execute();
        if($row['status']==1 || $row['status']==-1)
        {
            cls_msgbox::show('系统提示', "你好，该实例已被审核过了", "-1");
        }
        if($is_audit==1) {
            //$info=db::get_one("select *from #PB#_case where id='{$id}'");
            $info = db::select($this->file_all)->from($this->table)->where('id', $id)->as_row()->execute();
            $tasknums = req::item('tasknums');
            $info_data = req::$posts;
            $vid = req::item('vid');
            $vids = explode(',', $vid);
            //获取涉案信息
            $inv_log = db::select("id,sta,member_id,case_id,vid")->from(pub_mod_table::INVOLVED_LOG)->where('case_id', $id)->execute();
            //针对对象修改的情况，需清除一下之前的信息再推送给客户
            if ($info_data['object'] != $info['object']) {
                //如果存在则把之前已匹配的信息删除掉
                if (!empty($inv_log)) {
                    foreach ($inv_log as $v) {
                        db::delete(pub_mod_table::INVOLVED_LOG)->where('id', $v['id'])->execute();
                    }
                }

                $v_list = db::select($this->vfield)->from(pub_mod_table::TARGET_VIGILANT)->where('id', 'in', $vids)->execute();
                if (!empty($v_list)) {
                    foreach ($v_list as $k => $val) {
                        //$object_info = mod_common::return_project_val($val['target_id']);
                        $val['id'] = util::random('web'); //插入涉案表的id
                        $val['case_id'] = $id;
                        $val['sid'] = !empty($v['sid']) ? $v['sid'] : 0; //来源id
                        $val['infor_type'] = !empty($v['infor_type']) ? $v['infor_type'] : 0; //来源类型
                        $val['is_push'] = 1;  //推送给客户
                        $val['vid'] = $v_list[$k]['id'];
                        $val['case_object'] = $info_data['object'];
                        $val['push_time'] = KALI_TIMESTAMP; //推送时间
                        $val['project_id'] = !empty($val['project_id']) ? $val['project_id'] : '';
                        $val['appid'] = !empty($val['appid']) ? $val['appid'] : '';
                        unset($val['target_id']);
                        unset($val['object_id']);
                        //写入日志操作日志
                        $array['type'] = '2';
                        $array['do_time'] = KALI_TIMESTAMP;
                        $array['do_ip'] = req::ip();
                        $array['username'] = $this->userinfo['username'];
                        $array['sta'] = 0;//$v['sta'];
                        $array['ivo_id'] = $val['id'];
                        $array['msg'] = '等待确认';
                        mod_common::save_case_log($array); //写入日志
                        db::insert(pub_mod_table::INVOLVED_LOG)->set($val)->execute();
                    }
                }

            } else { //直接推送给客户
                //写入日志操作日志
                if (!empty($vids)) {
                    db::delete(pub_mod_table::INVOLVED_LOG)
                        ->where('case_id', $id)
                        ->where('vid', "not in", $vids)
                        ->execute();
                    foreach ($vids as $v) {
                        $array['type'] = '2';
                        $array['do_time'] = KALI_TIMESTAMP;
                        $array['do_ip'] = req::ip();
                        $array['username'] = $this->userinfo['username'];
                        $array['sta'] = 0;//$v['sta'];
                        $array['ivo_id'] = $v;
                        $array['msg'] = '等待确认';
                        mod_common::save_case_log($array); //写入日志
                    }
                    //推送给客户
                    $res = db::update(pub_mod_table::INVOLVED_LOG)
                        ->set(['is_push' => 1, 'push_time' => KALI_TIMESTAMP])
                        ->where("case_id", $id)
                        ->where("vid", "in", $vids)
                        ->execute();
                } else {
                    //清除一下数据
                    db::delete(pub_mod_table::INVOLVED_LOG)->where('case_id', $id)->execute();
                }

            }
            $remark = req::item('remark');
            $info_data['status'] = '1';  //规范性审核为通过
            $info_data['unit'] = !empty($info_data['unit']) ? $info_data['unit'] : 0;
            if ($info_data['unit'] != $info['unit']) {
                db::query("update `#PB#_host_unit` set `case_count`=case_count-1 where `id`='{$info['unit']}' ")->execute();
                db::query("update `#PB#_host_unit` set `case_count`=case_count+1 where `id`='{$info_data['unit']}' ")->execute();
            }
            $info_data['tasknum'] = '';
            $info_data['file_status'] = !empty($info_data['file_status']) ? $info_data['file_status'] : 0;
            $info_data['manage_nature'] = !empty($info_data['manage_nature']) ? $info_data['manage_nature'] : 0;
            $info_data['date'] = !empty($info_data['date']) ? strtotime($info_data['date']) : 0;
            $info_data['oversee_status'] = !empty($info_data['oversee_status']) ? $info_data['oversee_status'] : 0;
            //任务号修改
            if (!empty($tasknums)) {
                $tasknums = explode(",", $tasknums);
                foreach ($tasknums as $num) {
                    if (!preg_match('/^[0-9a-zA-Z_、]+$/', $num)) {
                        cls_msgbox::show('系统提示', '危机任务号只能是大小写字母加数字及下划线', '-1');
                        exit();
                    }
                }
                $info_data['tasknum'] = $tasknum = implode('、', array_unique($tasknums));
            }
            //项目号修改
            $project_num = req::item('project_num');
            if (!empty($project_num)) {

                //兼容之前的数据处理
                $project_num = explode(",", $project_num);
                foreach ($project_num as $num) {
                    if (!preg_match('/^[0-9a-zA-Z\_\-、]+$/', $num)) {
                        cls_msgbox::show('系统提示', '项目号只能是大小写字母加数字及下划线及中划线', '-1');
                        exit();
                    }
                }
                $info_data['project_num'] = implode('、', array_unique($project_num));

            } else {
                $info_data['project_num'] = '';
            }
            $case_handle_man_arr = array();
            //案例处理人
            if (!empty($info_data['case_handle_man'])) {
                $case_handle_man_arr = $info_data['case_handle_man'];
                $info_data['case_handle_man'] = implode('、', array_unique($info_data['case_handle_man']));

            } else {
                $info_data['case_handle_man'] = '';
            }

            //案件分类改为多选的处理
            if (!empty($info_data['casetype'])) {
                $info_data['casetype'] = implode('、', array_unique($info_data['casetype']));
            } else {
                $info_data['casetype'] = '0';
            }
            //定义变量屏蔽xdebug的报错
//        $info_data['reg_object'] = isset($info_data['reg_object']) ? $info_data['reg_object'] : '';
//        if ($info_data['reg_object'] == '2') {
//            $info_data['object'] = 0;
//            $reg_object_info["name"] = $info_data['not_reg_object'];
//            $reg_object_info["case_id"] = $id;
//            $reg_object_info["create_time"] = KALI_TIMESTAMP;
//            $reg_object_info["create_user"] = kali::$auth->user['uid'];
//
//
//        } elseif ($info_data['reg_object'] == '1') {
//            //如果案件相关性存在的情况下 修改注册对象时不修改相关性
//            if ($info['manage_nature']) {
//                $info_data['not_reg_object'] = '';
//            } else {
//                $info_data['not_reg_object'] = '';
//                //获取针对对象相关性()
//                $object_mn = db::select("mn_id")->from('#PB#_target_object')->where('id', $info_data['object'])->as_row()->execute();
//                //赋值为案件相关性
//                if (!empty($object_mn)) {
//                    $info_data['manage_nature'] = $object_mn['mn_id'];
//                } else {
//                    $info_data['manage_nature'] = 0;
//                }
//            }
//        } else {
//            $info_data['not_reg_object'] = '';
//            $info_data['object'] = 0;
//        }

            $info_data['source_option'] = !empty($info_data['source_option']) ? $info_data['source_option'] : ''; //来源方式删除
            if ($info_data['source_option'] == 1) {

                $info_data['source_unit'] = !empty($info_data['member_user']) ? $info_data['member_user'] : '';       //来源方式删除

            } elseif ($info_data['source_option'] == 2) {

                $info_data['source_unit'] = !empty($info_data['host_unit_mine']) ? $info_data['host_unit_mine'] : '';       //来源方式删除

            } elseif ($info_data['source_option'] == 3) {

                $info_data['source_unit'] = !empty($info_data['sh_host_unit']) ? $info_data['sh_host_unit'] : '';       //来源方式删除

            } elseif ($info_data['source_option'] == 4) {
                $info_data['source_unit'] = !empty($info_data['host_unit_unreg']) ? $info_data['host_unit_unreg'] : '';       //来源方式删除
            }
            $info['manage_nature'] = !empty($info_data['manage_nature']) ? $info['manage_nature'] : 0;
            //新增督办单位
            //$info_data['oversee_unit']  = !empty($info_data['oversee_unit'])?implode("、",$info_data['oversee_unit']):'';
            $info_data['oversee_unit'] = !empty($info_data['oversee_unit']) ? implode('、', array_unique($info_data['oversee_unit'])) : '';
            //经过规范性审核后文件上传的数据要清空一下
            $info_data['unreg_field'] = '';
            //注销不需要入库的数组
            unset($info_data['host_unit_mine']);
            unset($info_data['vid']);
            unset($info_data['host_unit_unreg']);
            unset($info_data['sh_host_unit']);
            unset($info_data['member_user']);
            unset($info_data['source_option']);
            unset($info_data['gourl']);
            unset($info_data['typeback']);
            unset($info_data['tasknums']);
            unset($info_data['ct']);
            unset($info_data['ac']);
            unset($info_data['remark']);
            unset($info_data['is_audit']);
            db::start();
            //非注册对象的操作
//        if (!empty($info_data['not_reg_object'])) {
//            $update_set['isdeleted'] = '1';
//            db::update('#PB#_target_object_unreg')->set($update_set)->where('case_id', $info['id'])->execute();
//            list($reg_object_info_id, $rows_affected) = db::insert('#PB#_target_object_unreg')->set($reg_object_info)->execute();
//            if ($reg_object_info_id <= 0) {
//                db::rollback();
//                cls_msgbox::show('系统提示', "非注册对象添加失败", -1);
//            }
//        }
            if (!empty($info_data['unit'])) {
                mod_related_personnel::set_person($info_data['unit'], $id); //社会单位联系人到相关人员
            }
            if (!empty($info_data['filing_unit'])) {
                mod_related_personnel::set_person($info_data['filing_unit'], $id); //社会单位联系人到相关人员
            }


            //$res  = db::update('#PB#_case',$info_data, "`id`='{$id}'");
            unset($info_data['csrf_token_name']);
            $res = db::update('#PB#_case')->set($info_data)->where('id', $id)->execute();
            //写入flow表
            $msg = '已通过';
            $flow_data = array(
                'check_time' => KALI_TIMESTAMP,
                'check_user' => $this->userinfo['uid'],
                'tc_table' => 'case',  //对应case表
                'tid' => $info_data['id'],
                'audit_type' => '1',  //审核类型 1规范性  0其他审核
                'reject_type' => 'status',   //审核类型
                'audit_sta' => '1',  //审核状态
                'why' => $remark
            );

            $insert_id = mod_flow_rule::flow_save($flow_data);
            db::commit();

            if (!empty($case_handle_man_arr)) {
                //获取案件名称
                $case_name = mod_case::get_case_name($id);
                foreach ($case_handle_man_arr as $v) {
                    cls_potato::send(mod_case::get_potato($v), $case_name, 'message');
                }
            }
            kali::$auth->save_admin_log("修改了规范性审核，并通过 {$id}的规范性审核");
            if ($res) {
                //跳转到待审核页面
                cls_msgbox::show('系统提示', "规范性审核已经通过", "?ct=case&ac=case_audit&audit=status&status=9&id=$id");
            } else {
                cls_msgbox::show('系统提示', "执行失败，请重新提交", "-1");
            }
        }else{
            //驳回的操作
            $update_data = [
                            'status'=>-1,
            ];
            $remark = req::item("remark");
            $res  =  db::update(pub_mod_table::CASE)->set($update_data)->where('id',$id)->execute();
            $flow_data = array(
                'check_time' => KALI_TIMESTAMP,
                'check_user' => $this->userinfo['uid'],
                'tc_table' => 'case',  //对应case表
                'tid' => $id,
                'audit_type' => '1',  //审核类型 1规范性  0其他审核
                'reject_type' => 'status',   //审核类型
                'audit_sta' => '-1',  //审核状态
                'why' => $remark
            );

            $insert_id = mod_flow_rule::flow_save($flow_data);
            if ($res) {
                //跳转到待审核页面
                cls_msgbox::show('系统提示', "规范性审核已被驳回，此实例废弃", "?ct=case&ac=case_audit&audit=status&status=-1&id=$id");
            } else {
                cls_msgbox::show('系统提示', "执行失败，请重新提交", "-1");
            }
        }

    }

    /**
     * 返回我们单位的json
     */
    public function host_unit_mine()
    {
        $keywords = req::item('q', '');
        $where = array(
            array('status', '=', '1'),
            array('isdeleted', '=', '0'),
        );
        //$sql = "select * from  `#PB#_host_unit`  where `status`='1' AND `isdeleted`='0'";
        //$data = db::get_all($sql);
        //$data = db::select()->from('#PB#_host_unit')->where($where)->execute();
        $data = db::select("id,name,parent_path")->from('#PB#_host_unit_mine')->where($where)->execute();

        //数组模糊搜索
        if (!empty($keywords)) {
            foreach ($data as $keys => $values) {
                if (strstr($values['name'], $keywords) !== false) {
                    $arr2[$keys]['id'] = $data[$keys]['id'];
                    $arr2[$keys]['name'] = $data[$keys]['name'];
                    $arr2[$keys]['parent_path'] = !empty($data[$keys]['parent_path']) ? $data[$keys]['parent_path'] : '';
                }
            }
            $arr2 = array_values($arr2);

        } else {
            $arr2 = $data;
        }
        $return = array();
        if (!empty($arr2)) {
            foreach ($arr2 as $k => $v) {

                $parent_top = explode(',', $v['parent_path']);

                $parent_top_name = mod_host_unit::get_mine_name($parent_top[count($parent_top) - 1]);
                $return['results'][$k]['id'] = "";
                $return['results'][$k]['text'] = "请选择";
                if (!empty($parent_top_name)) {
                    $return['results'][$k]['id'] = $v['id'];
                    $return['results'][$k]['text'] = $parent_top_name . ' 下属 ' . $v['name'];
                } else {
                    $return['results'][$k]['id'] = $v['id'];
                    $return['results'][$k]['text'] = $v['name'];
                }

            }
        }
        echo json_encode($return, JSON_UNESCAPED_UNICODE);

    }

    /**
     * 返回社会单位的json格式
     * 需要拼接字符串数组再去搜..
     */
    public function ajax_host_unit_select()
    {
        $keywords = req::item('q', '');
        $where = array(
            array('status', '=', '1'),
            array('isdeleted', '=', '0'),
            array('unit_type', '=', '1'),
        );
        //$sql = "select * from  `#PB#_host_unit`  where `status`='1' AND `isdeleted`='0'";
        //$data = db::get_all($sql);
        //$data = db::select()->from('#PB#_host_unit')->where($where)->execute();
        $data = db::select("id,name,parent_path")->from('#PB#_host_unit')->where($where)->execute();

        //数组模糊搜索
        if (!empty($keywords)) {
            foreach ($data as $keys => $values) {
                if (strstr($values['name'], $keywords) !== false) {
                    $arr2[$keys]['id'] = $data[$keys]['id'];
                    $arr2[$keys]['name'] = $data[$keys]['name'];
                    $arr2[$keys]['parent_path'] = !empty($data[$keys]['parent_path']) ? $data[$keys]['parent_path'] : '';
                }
            }
            $arr2 = array_values($arr2);

        } else {
            $arr2 = $data;
        }
        $return = array();
        foreach ($arr2 as $k => $v) {
            $parent_top_arr = explode(',', $v['parent_path']);
            $parent_top_id = $parent_top_arr[count($parent_top_arr) - 1];
            $arr2[$k]['parent_top_id'] = $parent_top_id;
        }
        mod_common::load_one($arr2, '#PB#_host_unit', 'parent_top_id', 'id,name', 'id', 'parent');
        foreach ($arr2 as $k => $item) {
            if (isset($item['parent']['name'])) {
                $return['results'][$k]['id'] = $item['id'];
                $return['results'][$k]['text'] = $item['parent']['name'] . ' 下属 ' . $item['name'];

            } else {
                $return['results'][$k]['id'] = $item['id'];
                $return['results'][$k]['text'] = $item['name'];
            }
        }

        echo json_encode($return, JSON_UNESCAPED_UNICODE);

    }

    /**
     * 统一返回json格式类
     * $type = 返回数据表
     **/

    public function ajax_select()
    {
        $type = req::item('type');
        $status = req::item('status', '1');
        $display = req::item('display', '');
        $sort = req::item('sort', '');
        $privacy_level = req::item('privacy_level', '');
        if (empty($sort)) {
            $order = 'id';
            $sort = 'asc';
        }
        $where[] = array('isdeleted', '=', 0);
        if (!empty($status) and $status != 0) {
            $where[] = array('status', '=', 1);
        }
        //是否显示
        if (!empty($display) and $display != 0) {
            $where[] = array('is_display', '=', 1);
        }
        //是否打开排序
        if ($sort == 'open') {
            // $order_by = " order by `sort` asc";
            $order = 'sort';
            $sort = 'asc';
        }


        //保密等级的排序
        if ($sort == 'sort_level') {
            $order = 'level';
            $sort = 'asc';
        }


        if (!empty($privacy_level)) {

            if (empty($id) && $this->userinfo['groups'] != 1) {
                $id = isset($this->userinfo['secrecy_level']) ? $this->userinfo['secrecy_level'] : '';
                if (isset($id) && $id != '') {
                    $level = db::select('level')->from('#PB#_secrecy')->where('id', $id)->as_row()->execute();
                    $where[] = ['level', '<=', $level['level']];
                }
            }
        }

        $table = "#PB#_" . $type;
        $keyword = req::item('q', '');
        if (!empty($keyword)) {
            $where[] = array('name', 'like', "%{$keyword}%");
        }
        $data = db::select("id,name")->from($table)->where($where)->order_by($order, $sort)->execute();
        $return = array();
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $return['results'][$k]['id'] = $v['id'];
                $return['results'][$k]['text'] = $v['name'];
            }
        }
        echo json_encode($return, JSON_UNESCAPED_UNICODE);

    }

    /**
     *
     */
    public static function ajax_base_select()
    {
        $table = req::item('table');
        $table = "#PB#_" . $table;
        $keyword = req::item('q', '');
        $where[] = array('delete_time', '=', 0);
        if (!empty($keyword)) {
            $where[] = array('name', 'like', "%{$keyword}%");
        }
        $data = db::select("id,name")->from($table)->where($where)->execute();
        $return = array();
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $return['results'][$k]['id'] = $v['id'];
                $return['results'][$k]['text'] = $v['name'];
            }
        }
        echo json_encode($return, JSON_UNESCAPED_UNICODE);
    }

    /**
     * ajax获取客户列表
     **/
    public static function ajax_member()
    {
        //不包含超级管理员
        $q = req::item('q', '');
        $field = req::item('field', 'nickname');  //默认只显示称呼
        $where[] = array('isdeleted', '=', 0);
        $where[] = array('parent_id', '=', 0); //只显示母
        $where[] = array('status', '=', 1);  //正常客户
        if (!empty($q)) {
            $where[] = array($field, 'like', "%$q%");
        }
        $data = db::select("uid,$field")->from("#PB#_member")->where($where)->execute();
        $return = array();
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $return['results'][$k]['id'] = $v['uid'];
                $return['results'][$k]['text'] = $v[$field];
            }
        }
        echo json_encode($return, JSON_UNESCAPED_UNICODE);
    }

    /**
     * ajax获取管理员列表
     */
    public static function ajax_admin_user()
    {
        //不包含超级管理员
        $no_admin = req::item('no_admin', '');
        $q = req::item('q', '');
        if (!empty($no_admin)) {
            $where[] = array('groups', '!=', '1');
        }
        if (!empty($q)) {
            $where[] = array('username', "like", "%$q%");
        }
        //$data = mod_case::get_all_data("#PB#_admin",['uid,username'],$no_admin);
        $data = db::select("uid,username")->from("#PB#_admin")->where($where)->execute();
        $return = array();
        if (!empty($data)) {
            foreach ($data as $k => $v) {
                $return['results'][$k]['id'] = $v['uid'];
                $return['results'][$k]['text'] = $v['username'];
            }
        }

        echo json_encode($return, JSON_UNESCAPED_UNICODE);
    }


    /**
     * 实例文件上传
     **/
    public function import_page()
    {
        tpl::assign('action', '?ct=case&ac=import&type=99');

        tpl::display('record.page.tpl');
    }

    //上传
    public function import()
    {
        $file = req::item('accessory');
        $name_info = explode(",", $file[0]);
        $pass_name = $name_info['0'];
        $real_name = $name_info['1'];
        $tmp_path = PATH_ROOT . '/uploads' . '/tmp';
        $file_path = PATH_ROOT . '/uploads' . '/file';
        //生成key
        $key = util::random('unique');
        //循环读取文件信息
        $tmp_path = $tmp_path . "/" . $pass_name;

        if (file_exists($tmp_path)) {
            // 获取临时图片内容
            $info = pathinfo($tmp_path);
            $file_size = filesize($tmp_path);
            $plaintext = file_get_contents($tmp_path);
            $value = cls_crypt::encode($plaintext, $key);
            $file_name = 'case_' . date('YmdHis') . '.' . $info['extension'];
            // 生成加密图片
            file_put_contents($file_path . '/' . $file_name, $value);
            //删除临时调用图片
            unlink($tmp_path);
            //构造入库数据
            $data = array(
                'file' => $file_name,
                'key' => $key,
                'type' => 99,
                'size' => $file_size,
                'filename' => $real_name,
                'create_user' => $this->userinfo['uid'],
                'create_time' => KALI_TIMESTAMP,
            );
            list($insert_id, $rows_affected) = db::insert('#PB#_record_files')->set($data)->execute();
            kali::$auth->save_admin_log("实例文件上传成功id为:$insert_id");
        }
        if (isset($insert_id) && !empty($insert_id)) {
            cls_msgbox::show('系统提示', "上传成功，请等待数据导入，等待时间约为一分钟", -3);
        }

    }

    //select2 ajax 返回实例
    public function ajax_get_case_select()
    {
        $keywords = req::item('q', '');
        if (empty($keywords)) {
            echo json_encode(['results' => []], JSON_UNESCAPED_UNICODE);
        }
        $array = (array)db::select('id,name')->from('#PB#_case')->where([
            ['isdeleted', '=', '0'], ['name', 'like', "%{$keywords}%"]
        ])->order_by('create_time', 'desc')->execute();
        $data = [];
        foreach ($array as $k => $item) {
            $data['results'][$k]['id'] = $item['id'];
            $data['results'][$k]['text'] = $item['name'];
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    //bsSuggest ajax 返回实例
    public function ajax_get_case_bssuggest()
    {
        $keywords = req::item('keyword', '');
        if (empty($keywords)) {
            echo json_encode(['value' => []], JSON_UNESCAPED_UNICODE);
        }
        $array = (array)db::select('id,name')->from('#PB#_case')->where([
            ['isdeleted', '=', '0'], ['name', 'like', "%{$keywords}%"]
        ])->order_by('create_time', 'desc')->execute();
        $data = [];
        foreach ($array as $k => $item) {
            $data['value'][$k]['id'] = $item['id'];
            $data['value'][$k]['name'] = $item['name'];
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    //获取格式化后的涉案数据
    public function _inv_list_log($table = '', $case_info = [])
    {

        $new_list = array();
        $list = db::select($this->inv_field)
            ->from($table)
            ->where("case_id", $case_info['id'])
            ->and_where("content", "like", "%{$case_info['object']}%")
            ->and_where("is_tmp", "!=", 1)
            ->execute();
        if (!empty($list)) {
            foreach ($list as $k => $v) {
                $company = db::select("name")->from(pub_mod_table::COMPANY)->where("id", $v['company_id'])->as_row()->execute();
                $nickname = db::select("nickname")->from(pub_mod_table::MEMBER)->where("uid", $v['member_id'])->as_row()->execute();
                $new_list[$k]['id'] = $v['id'];
                $new_list[$k]['content'] = $v['content'];
                $new_list[$k]['sta'] = $v['sta'];
                $new_list[$k]['member_nickname'] = $nickname['nickname'];
                $new_list[$k]['push_time'] = $v['push_time'];
                $new_list[$k]['company_name'] = !empty($company['name']) ? $company['name'] : '-';
                if ($v['infor_type'] != 2) {
                    $new_list[$k]['project_name'] = array_key_exists($v['project_id'], $this->object) ? $this->object[$v['project_id']] : '-';
                    $new_list[$k]['app_name'] = array_key_exists($v['appid'], $this->object) ? $this->object[$v['appid']] : '-';
                } else {
                    $new_list[$k]['project_name'] = self::_get_object_str($v['project_id'], $this->object);
                    $new_list[$k]['app_name'] = self::_get_object_str($v['appid'], $this->object);
                }
            }
        }
        return $new_list;
    }

    public function _inv_list_log_tmp($table = '', $case_info = [])
    {
        $new_list_tmp = array();
        $list_tmp = db::select($this->inv_field)
            ->from($table)
            ->where("case_id", $case_info['id'])
            ->and_where("content", "like", "%{$case_info['object']}%")
            ->and_where("is_tmp", "!=", 1)
            ->execute();
        if (!empty($list_tmp)) {
            foreach ($list_tmp as $k => $v) {
                $company = db::select("name")->from(pub_mod_table::COMPANY)->where("id", $v['company_id'])->as_row()->execute();
                $nickname = db::select("nickname")->from(pub_mod_table::MEMBER)->where("uid", $v['member_id'])->as_row()->execute();
                $new_list_tmp[$k]['id'] = $v['id'];
                $new_list_tmp[$k]['sta'] = $v['sta'];
                $new_list_tmp[$k]['member_nickname'] = $nickname['nickname'];
                $new_list_tmp[$k]['content'] = $v['content'];
                $new_list_tmp[$k]['company_name'] = !empty($company['name']) ? $company['name'] : '-';
                if ($v['infor_type'] != 2) {
                    $new_list_tmp[$k]['project_name'] = array_key_exists($v['project_id'], $this->object) ? $this->object[$v['project_id']] : '-';
                    $new_list_tmp[$k]['app_name'] = array_key_exists($v['appid'], $this->object) ? $this->object[$v['appid']] : '-';
                } else {
                    $new_list_tmp[$k]['project_name'] = self::_get_object_str($v['project_id'], $this->object);
                    $new_list_tmp[$k]['app_name'] = self::_get_object_str($v['appid'], $this->object);
                }
            }
        }
        return $new_list_tmp;
    }
    
}






