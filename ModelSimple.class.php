<?php
abstract class Model{
	protected $tableName="";//在子类中重写
	protected $mysqlOb="";
	function __construct(){
		$this->mysqlOb=Mysql::getInstance();
	}
	//增
	function add($arr){
		//拼sql语句 insert into cms_message(字段列表) values(值列表)
		$fieldList="";//字段列表
		$valueList="";//值列表
		foreach($arr as $k=>$v){
			$fieldList.=",".$k;
			$valueList.=",'".$v."'";
		}
		$fieldList=substr($fieldList,1);
		$valueList=substr($valueList,1);
		//拼sql语句
		$sql="insert into {$this->tableName}({$fieldList}) values({$valueList})";
		//执行
		return $this->mysqlOb->query($sql);
	}
	//删
	/*
	 * param:$where  
	 */
	function delete($where=""){
		$where = !empty($where) ? "where ".$where : "";
		$sql="delete from {$this->tableName} {$where}";
		return $this->mysqlOb->query($sql);
	}
	//改
	/*
	 * param $arr array('字段名'=>值,.....)
	 * param $where 类似 id=5 
	 */
	function save($arr,$where=""){
		//update 表名 set 名值对列表 where 条件
		$where = !empty($where) ? "where ".$where : "";
		$fvList="";
		foreach($arr as $k=>$v){
			$fvList.=",{$k}='{$v}'";
		}
		$fvList=substr($fvList,1);
		$sql="update {$this->tableName} set {$fvList} {$where}";
		return $this->mysqlOb->query($sql);
	}
	//查
	function select($field="*",$where="",$limit="",$order=""){//单表查询
		$where = !empty($where) ? "where ".$where : "";
		$limit = !empty($limit) ? "limit ".$limit : "";
		$order = !empty($order) ? "order by ".$order : "";
		//select 字段列表 from 表名 where 条件 order by 字段  asc|desc limit 内容
		$sql="select {$field} from {$this->tableName} $where $order $limit";
		return $this->mysqlOb->query($sql);
	}
	//query
	function query($sql){
		return $this->mysqlOb->query($sql);
	}	
}