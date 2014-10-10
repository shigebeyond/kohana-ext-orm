<?php defined('SYSPATH') or die('No direct script access.');

/**
 * 多文件上传的处理器
 * 
 * @Package  
 * @category file upload
 * @author shijianhang
 * @date Oct 23, 2013 11:03:57 PM 
 *
 */
class Upload_Multi  
{  
	/**
	 * 工厂
	 * @param string $field
	 * @return Upload_Multi|boolean
	 */
	public static function factory($field)
	{
		$item = new Upload_Multi($field);
		
		//准备多文件上传
		if ($item->_prepare()) 
		{
			return $item;
		}
		
		return FALSE;
	}
	
	/** 上传文件的字段 */
	protected $_field;
	
	/** 上传文件个数 */
	protected $_file_count;
	
	/**
	 * 构造函数
	 * @param string $field
	 */
	protected function __construct($field)
	{
		$this->_field = $field;
		// $this->_file_count = count($_FILES[$this->_field][tmp_name]);
		$this->_file_count = count(Arr::path($_FILES, "$this->_field.tmp_name"));
	}
	
	/**
	 * 准备多文件上传
	 * 多文件上传时，将$_FILES由数组转变为hash
	 * 如 
	 * $_FILE = array(
	 *   'file' => array(
	 *   	'tmp_name' => array('a.txt', 'b.txt')
	 *   ),
	 * )
	 * 转变为
	 * $_FILE = array(
	 *   'file1' => array(
	 *   	array('tmp_name' => 'a.txt'),
	 *   ),
	 *   'file2' => array(
	 *   	aarray('tmp_name' => 'b.txt'),
	 *   ),
	 * )
	 */
	protected function _prepare()
	{
		if (!isset($_FILES[$this->_field]))
		{
			return FALSE;
		}
		
		$upload = $_FILES[$this->_field];
		
		//单文件上传
		if (!is_array($upload['tmp_name']))
		{
			// param_name is a single object identifier like "file",
			// $_FILES is a one-dimensional array:
			return FALSE;
		}
		
		// 多文件上传
		// param_name is an array identifier like "files[]",
		// $_FILES is a multi-dimensional array:
		unset($_FILES[$this->_field]);
		
		foreach ($upload as $key => $arr)
		{
			foreach ($arr as $index => $value)
			{
				$_FILES[$this->field($index)][$key] = $value;
			}
		}
			
		return TRUE;
	}
	
	/**
	 * 获得上传文件的个数
	 */
	public function count_files()
	{
		return $this->_file_count;
	}
	
	/**
	 * 根据游标来获得上传文件的字段
	 *
	 * @param index
	 * @return string
	 */
	public function field($index)
	{
		return $this->_field.'_'.$index;
	}
	
	/**
	 * 获得指定游标的上传文件
	 * @param int $index
	 * @return array
	 */
	public function file($index)
	{
		return $_FILES[$this->field($index)];
	}
	
	/************************* 校验 ***************************/
	/**
	 * 构建上传文件的验证器
	 * 
	 * @param int $index 上传文件的游标
	 * @param string $label 上传文件的标签
	 * @param array $expected_exts 期望的文件扩展名, 类型限制
	 * @param string $size 大小限制, 如1M
	 * @return Validation
	 */
	public function validation($index, $label = NULL, array $expected_exts = NULL, $size = NULL) 
	{
		$field = $this->field($index);
		return Upload::validation($_FILES, $field, $label, $expected_exts, $size, FALSE)
			->rule($field, array($this, 'unique'), array(':value', $index)); //校验文件唯一性
		
	}
	
	/**
	 * Tests if the upload file data is unique.
	 *
	 * @param array $file
	 * @param string $index
	 * @return bool
	 */
	public function unique(array $file, $index)
	{
		// 获得上传文件的md5值: 注意此处会修改 $_FILES[$this->_field] 的值, 为他添加md5属性
		$_FILES[$this->field($index)]['md5'] = $md5 = MD5(file_get_contents($file['tmp_name']));
	
		// 比较已有文件的md5值
		//$mf = ORM::factory('File')->where('md5', '=', $md5)->find();
		$mf = ORM::factory('File', $md5);
	
		// 处理重复的文件
		//return !$mf->loaded(); // 重複的文件報錯
		if($_FILES[$this->field($index)]['exist'] = $mf->loaded())// 重複的文件直接重用
		{
			//Message::warn("上传文件[{$file['name']}]与已有文件[{$mf->filename}]重复");
		}
	
		return TRUE;
	}

	/************************* 保存 ***************************/
	/**
	 * 上传单个文件
	 *    保存文件内容 + 保存文件描述信息
	 *
	 * @param int $index 上传文件的游标
	 * @param string $code 标识
	 * @return boolean|string md5
	 */
	public function save_ext($index, $code = NULL)
	{
		return Upload::save_ext($this->file($index), $code);
	}
} 
