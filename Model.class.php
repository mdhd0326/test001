<?php
	//Model类主要用来操作数据库
	class Model{
		private $dbHost;
		private $dbUser;
		private $dbPass;
		private $dbCharset;
		private $dbName;
		private $dbPrefix;
		private $tabFields;
		private $tabName;
		private $result;
		private $link;
		private $where;//where条件
		private $order;//order排序条件
		private $getLimit;//仅供delete和update使用的limit limit 5
		private $limit;//limit子句  limit0,5
		private $field;
		
		//初始化成员
		public function __construct($tabName){
			global $config;
			$this->tabName = $tabName;
			$this->dbHost = $config['DB_HOST'];
			$this->dbUser = $config['DB_USER'];
			$this->dbPass = $config['DB_PWD'];
			$this->dbCharset = $config['DB_CHARSET'];
			$this->dbPrefix = $config['DB_PREFIX'];
			$this->dbName = $config['DB_NAME'];
			$this->connect();//连接数据库
			$this->getFields();//缓存字段
		}
		
		private function getFields(){
			$cache = "./Runtime/Cache/".$this->dbPrefix.$this->tabName.".php";
		
			//判断缓存文件是否存在
			//如果缓存文件存在，直接读取缓存文件
			if(file_exists($cache)){
				//直接读取缓存
				$this->tabFields = include $cache;
			}else{
				//查询数据表的结构
				$sql = "desc ".$this->dbPrefix.$this->tabName;
				//执行查询，返回二维数组
				$data = $this->query($sql);
				$fields = array();
				//遍历这个数组
				foreach($data as $val){
					//将所有的字段名称保存下来
					$fields[] = $val['Field'];
					//将主键保存下来
					if($val['Key']=='PRI'){
						$fields['_pk'] = $val['Field'];
					}
					//将自增键保存下来
					if($val['Extra']=='auto_increment'){
						$fields['_auto'] = $val['Field'];
					}
				}
				$this->tabFields = $fields;
				//将查询出的字段进行缓存
				//$data = include "./cache/user.php";
				//var_dump($fields);
				$str = "<?php\nreturn ".var_export($fields,true).";";
				file_put_contents("./Runtime/Cache/".$this->dbPrefix.$this->tabName.".php",$str);
			}
		}
	
		//天龙八部
		private function connect(){
			//1.连接数据库
			$this->link = mysql_connect($this->dbHost,$this->dbUser,$this->dbPass);
			//2.判断错误
			if(!$this->link){
				return false;
			}
			//3.设置字符集
			if(!mysql_query("set names ".$this->dbCharset)){
				return false;
			}
			//4.选择数据库
			if(!mysql_select_db($this->dbName)){
				return false;
			}
		}
		
		//显示错误信息的方法
		public function getError(){
			return mysql_errno().":".mysql_error();
		}
		
		//完成执行sql语句的方法
		//execute:用来执行insert,delete,update语句的方法
		//返回受影响行数
		public function execute($sql){
			//将传入的sql语句发送给mysql服务器
			$res = mysql_query($sql);
			//处理结果
			if($res){
				return mysql_affected_rows();
			}else{
				//显示错误信息
				return false;
			}
		}
		
		//完成执行查询语句的方法select
		//query:用来执行select语句
		//返回二维数组
		public function query($sql){
			$res = mysql_query($sql);
			if($res){//如果执行成功
				$this->result = $res;//将结果集资源赋值
				if(mysql_num_rows($res)>0){//记录大于0条
					while($row=mysql_fetch_assoc($res)){
						$data[] = $row;
					}
					return $data;
				}else{//没有记录
					return array();
				}
			}else{//sql语句执行失败
				return false;
			}
		}
		
		//insert 
		//主要功能：完成数据的插入
		//参数：array(array('username'=>'','password'=>''),array())
		//返回：受影响行数
		public function insert($data=null){
			//如果没有传递参数
			if(is_null($data)){
				$data = $_POST;
			}
			//判断$data是否为空
			if(empty($data)){
				return false;
			}
			//组装sql语句
			//array(array('username'=>'zhangsan','password'=>'123','aaaa'=>'bbb')));
			if(is_array($data[0])){
				//二维数组
				//insert into user(username,password) values('zhangsan','123'),('lisi','123'),('wangwu','123')
				foreach($data[0] as $key=>$val){
					//如果该字段在字段数组当中不存在，直接删掉
					if(!in_array($key,$this->tabFields)){
						unset($data[0][$key]);
					}
				}
				//先组装$keys
				$keys = join(",",array_keys($data[0]));
				foreach($data as $val){
					$values .= "('".join("','",array_values($val))."'),";
				}
				$values = rtrim($values,",");
				$sql = "insert into ".$this->dbPrefix.$this->tabName."($keys) values".$values;
				
			}else{
				//一维数组
				//array('username'=>'zhangsa','password'=>'123');
				//insert into user(username,password) values('zhangsan','123')
				foreach($data as $key=>$val){
					if(!in_array($key,$this->tabFields)){
						continue;
					}
					$keys .= $key.",";//username,password,
					$values .= $val."','";
				}
				$keys = rtrim($keys,",");
				$values = "'".rtrim($values,",'")."'";
				$sql = "insert into ".$this->dbPrefix.$this->tabName."($keys) values($values)";
			}
			//执行sql语句
			return $this->execute($sql);
		}
		
		//删除操作
		//delete from user where id>1 order by age desc limit 3
		public function delete($where=null,$order=null,$limit=''){
			$sql = "delete from ".$this->dbPrefix.$this->tabName;
			//先来处理$where
			//1.判断$where是否为空
			if(empty($where)&&empty($this->where)){
				return false;
			}
			//2.考虑$where的内容类型
			if(empty($this->where)){
				$this->where($where);
			}
			
			//3.处理$order
			if(empty($this->order)){
				$this->order($order);
			}
			
			//4.处理limit
			if(empty($this->getLimit)){
				$this->getLimit($limit);
			}
			
			//5.拼接sql语句
			$sql = $sql.$this->where.$this->order.$this->getLimit;
			//6.执行sql语句
			return $this->execute($sql);
		}
		
		//更新数据
		//update shop_user set username='zhangsan',password='123' where id=1 order by id desc limit 5
		//array('id'=>1,'username'=>'zhangsan')
		public function update($data=null,$where='',$order='',$limit=''){
			$sql = "update ".$this->dbPrefix.$this->tabName;
			//1.先处理$data(要更新的字段)
			//不能为空
			if(is_null($data)){
				$data = $_POST;
			}
			
			//过滤字段
			foreach($data as $key=>$val){
				if(!in_array($key,$this->tabFields)){
					unset($data[$key]);
				}
			}
			
			if(empty($data)){
				return false;
			}
			if(is_string($data)){
				//字符串 username='zhangsan',password='123'
				$set = " set ".$data;
			}
			if(is_array($data)){
				//数组
				foreach($data as $key=>$val){
					//带主键 array('id'=>1,'username'=>'zhangsan')
					//不带主键 array('username'=>'zhangsan')
					//判断当前字段是否为主键
					if($key==$this->tabFields['_pk']){
						//作为条件
						$where2 = " where ".$key."='$val'";
					}else{
						//作为修改内容
						$s[] = $key."='$val'";
					}
				}
				$set = " set ".join(",",$s);
			}
			
			//处理$where
			if(empty($this->where)){
				if(!empty($where)){
					//判断$w是否存在
					if(!empty($where2)){
						//$w = ' where id=1 or id=1 and password=123';
						//array('id'=>1,'password'=>'123')
						//拼接传递进来的$where
						//2.考虑$where的内容类型
						//字符串 id>1 age>20 id=100
						if(is_string($where)){
							$this->where = $where2." or ".$where;
						}
						//判断$where是否为二维数组
						if(is_array($where[0])){
							//多条件
							//array(array('id'=>1,'sex'=>1),array('age'=>20)) id=1 or age=20
							foreach($where as $val){
								$w = array();
								foreach($val as $k=>$v){
									$w[] = $k."='$v'";//age=20
								}
								$condition[] = join(" and ",$w);//'id=1 and sex=1','age=20'
							}
							$this->where = join(' or ',$condition);
							$this->where = $where2." or ".$where;
						}else{
							//单条件
							//数组 array('username'=>'zhangsan','age'=>20)  username='zhangsan' and age='20'
							foreach($where as $key=>$val){
								$w[] = $key."='$val'";
							}
							$this->where = join(" and ",$w);
							$this->where = $where2." or ".$where;
						}
						
					}else{
						//1.判断$where是否为空
						if(empty($where)){
							return false;
						}
						//处理$where
						$this->where($where);
					}
				}else{
					if(empty($where2)){
						return false;
					}else{
						$this->where = $where2;
					}
				}
			}
			
			//order和limit
			if(empty($this->order)){
				$this->order($order);
			}
			
			//4.处理limit
			if(empty($this->getLimit)){
				$this->getLimit($limit);
			}
			
			//5.拼接sql语句
			$sql = $sql.$set.$this->where.$this->order.$this->getLimit;
			return $this->execute($sql);
		}
		
		//获取刚刚插入的主键id的值
		public function lastInsertId(){
			return mysql_insert_id($this->link);
		}
		
		//查询方法 select DQL
		//select * from user where order limit
		public function select($field='*',$where='',$order='',$limit=''){
			//处理字段 $field
			//string  id,username
			//array(id,username)
			if(empty($this->field)){
				$this->field($field);
			}
			
			//处理$where
			if(empty($this->where)){
				$this->where($where);
			}
			
			//order和limit
			if(empty($this->order)){
				$this->order($order);
			}
			
			//4.处理limit
			//先判断limit是否为空
			if(empty($this->limit)){
				$this->limit($limit);
			}
			
			$sql = "select ".$this->field." from ".$this->dbPrefix.$this->tabName.$this->where.$this->order.$this->limit;
			
			//执行sql语句
			return $this->query($sql);
		}
		
		//查询单条记录方法 find
		//select * from user limit 1
		public function find($field='*',$where='',$order=''){
			//处理字段 $field
			//string  id,username
			//array(id,username)
			if(empty($this->field)){
				$this->field($field);
			}
			
			//处理$where
			if(empty($this->where)){
				$this->where($where);
			}
			
			//处理order
			if(empty($this->order)){
				$this->order($order);
			}
			
			$sql = "select ".$this->field." from ".$this->dbPrefix.$this->tabName.$this->where.$this->order." limit 1";
			
			//执行sql语句
			$data = $this->query($sql);
			return $data[0];
		}
		
		//查询总数 total
		//select count(*) from user;
		
		//where方法（给where添加条件）
		public function where($where){
			//处理$where
			if(!empty($where)){
				//处理
				//string
				//array
				//2.考虑$where的内容类型
				//字符串 id>1 age>20 id=100
				if(is_string($where)){
					$where = " where ".$where;
				}else{
					//判断$where是否为二维数组
					if(is_array($where[0])){
						//多条件
						//array(array('id'=>1,'sex'=>1),array('age'=>20)) id=1 or age=20
						foreach($where as $val){
							$w = array();
							foreach($val as $k=>$v){
								$w[] = $k."='$v'";//age=20
							}
							$condition[] = join(" and ",$w);//'id=1 and sex=1','age=20'
						}
						$where = " where ".join(' or ',$condition);
					}else{
						//单条件
						//数组 array('username'=>'zhangsan','age'=>20)  username='zhangsan' and age='20'
						foreach($where as $key=>$val){
							$w[] = $key."='$val'";
						}
						$where = " where ".join(" and ",$w);
					}
				}
			}else{
				$where = "";
			}
			$this->where = $where;
			return $this;
		}
		
		//order方法 处理$order
		public function order($order){
			//order和limit
			//3.处理$order
			if(!empty($order)){
				//string 'id desc,age asc'
				if(is_string($order)){
					$order = ' order by '.$order;
				}
				//array  array('id'=>'asc','age'=>'desc')
				if(is_array($order)){
					foreach($order as $key=>$val){
						$o[] = $key." ".$val;
					}
					$order = " order by ".join(",",$o);
				}
			}else{
				$order = "";
			}
			$this->order = $order;
			return $this;
		}
		
		//getlimit
		private function getLimit($limit){
			//先判断limit是否为空
			if(empty($limit)){
				$limit = "";
			}else{
				//limit 3
				//delete from user where id>1 order by age asc limit 3
				//not array
				if(is_array($limit)){
					$limit = " limit ".(int)$limit[0];
				}else{
					$limit = " limit ".(int)$limit;
				}
			}
			$this->getLimit = $limit;
		}
		
		//limit
		public function limit($limit){
			if(empty($limit)){
				$limit = "";
			}else{
				//limit 3
				//delete from user where id>1 order by age asc limit 3
				//not array
				if(is_array($limit)){
					//array(5)
					if(count($limit)==1){
						$limit = " limit ".(int)$limit[0];
					}else{
						//array(5,5)
						$limit = " limit ".(int)$limit[0].",".(int)$limit[1];
					}
				}else{
					$limit = " limit ".(int)$limit;
				}
			}
			$this->limit = $limit;
			return $this;
		}
		
		//获取记录总数的方法
		public function total($where=''){
			//处理where
			$this->where($where);
			$sql = "select count(*) as c from ".$this->dbPrefix.$this->tabName.$this->where;
			$data = $this->query($sql);
			return $data[0]['c'];
		}
		
		public function field($field='*'){
			if(is_array($field)){
				foreach($field as $key=>$val){
					if(!in_array($val,$this->tabFields)){
						unset($field[$key]);
					}
				}
				$field = join(",",$field);
			}elseif(is_string($field)){
				$field = $field;
			}else{
				return false;
			}
			//判断$field是否为空
			if(empty($field)){
				$field = "*";
			}
			$this->field = $field;
			return $this;
		}
		
		//析构方法
		public function __destruct(){
			//销毁结果集资源
			if(is_resource($this->result)){
				mysql_free_result($this->result);
			}
			if(is_resource($this->link)){
				//关闭数据库连接
				mysql_close($this->link);
			}
		}
		
	}
	
	
	
	