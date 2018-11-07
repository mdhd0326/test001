<?php
class Mysql{//单例模式实现 
	protected static $myOb="";
	//不能使用new在类的外部实例化对象
	//连接数据
	protected function __construct(){
		//使用配置文件中的值
		$conn=mysql_connect(DB_HOST,DB_USERNAME,DB_PASSWORD);
		if(is_resource($conn)){
			$re=mysql_select_db(DB_NAME);
			if($re){
				mysql_set_charset(DB_CHARSET);
			}
		}
	}
	//通过一个静态方法产生一个对象
	public static  function getInstance(){
		if(self::$myOb==''){
			$ob=new self();
			self::$myOb=$ob;
		}
		return self::$myOb;
	}
	//防止对象被克隆
	protected function __clone(){
		
	}
	
	/*作用：执行sql语句
	*param:$sql  被执行的sql语句
	*return : select 返回二维数组
	*insert 返回主键id值
	*delete update 返回影响记录的条数
	*/
	function query($sql){
		$result=mysql_query($sql);
		if($result){
			if(preg_match("/^select/i",$sql)){
				$arr=array();
				while($row=mysql_fetch_assoc($result)){
					$arr[]=$row;
				}
				return $arr;
			}else if(preg_match("/^insert/i",$sql)){
				return mysql_insert_id();
			}else if(preg_match("/^(update|delete)/i",$sql)){
				return mysql_affected_rows();
			}else{
				return false;
			}
		}else{
			return false;
		}
	}
	//关闭数据库连接
	function __destruct(){
		mysql_close();
	}	
}








