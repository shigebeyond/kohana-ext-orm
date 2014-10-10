<?php defined('SYSPATH') or die('No direct script access.');

/**
 * 处理文件上传的请求
 * 
 * @Package  
 * @category file upload
 * @author shijianhang
 * @date Oct 23, 2013 11:03:57 PM 
 *
 */
class Upload extends Krishna_Upload 
{
	/************************* 校验 ***************************/
	/**
	 * 构建上传文件的验证器
	 * 
	 * @param array $upload 上传文件的信息
	 * @param string $field 上传文件的字段
	 * @param string $label 上传文件的标签
	 * @param array $expected_exts 期望的文件扩展名, 类型限制
	 * @param string $size 大小限制, 如1M
	 * @param bool $unique 是否唯一
	 * @return Validation
	 */
	public static function validation(array $upload, $field, $label = NULL, array $expected_exts = NULL, $size = NULL, $unique = TRUE) 
	{
		if (!isset($upload[$field]))
		{
			return NULL;
		}
		
		//构建校验器
		$validation = Validation::factory($upload);
		
		//针对$file[$field]属性进行校验
		$validation->rule($field, 'Upload::not_empty');//校验不为空，必须有上传文件才能进行下一步校验
			
		$validation->rule($field, 'Upload::valid'); //校验合法的上传数据，有name/type/size等属性
			
		if (!empty($expected_exts))
		{
			$validation->rule($field, 'Upload::type', array(':value', $expected_exts)); //校验文件类型
		}
			
		$size = empty($size) ? ini_get('upload_max_filesize') : $size;//默认的文件大小限制是2M
		$validation->rule($field, 'Upload::size', array(':value', $size)); //校验文件大小
			
		if ($unique) 
		{
			$validation->rule($field, 'Upload::unique', array(':value', $field)); //校验文件唯一性
		}
		
		return $validation->label($field, $label);
	}
	
	/**
	 * Tests if the upload file data is unique.
	 *
	 * @param array $file
	 * @param string $field
	 * @return bool
	 */
	public static function unique(array $file, $field)
	{
		// 获得上传文件后缀
		$fi = pathinfo($file['name']);;
		$ext = isset($fi['extension']) ? '.'.$fi['extension'] : '';
	
		// 获得上传文件的md5值: 注意此处会修改 $_FILES[$field] 的值, 为他添加md5属性
		$_FILES[$field]['md5'] = $md5 = MD5(file_get_contents($file['tmp_name'])).$ext;
	
		// 比较已有文件的md5值
		//$mf = ORM::factory('File')->where('md5', '=', $md5)->find();
		$mf = ORM::factory('File', $md5);
	
		// 处理重复的文件
		//return !$mf->loaded(); // 重複的文件報錯
		if($_FILES[$field]['exist'] = $mf->loaded())// 重複的文件直接重用
		{
			Message::warn("上传文件[{$file['name']}]与已有文件[{$mf->filename}]重复");
		}
		
		return TRUE;
	}
	
	/************************* 单文件上传与删除 ***************************/
	/**
	 * 上传单个文件
	 *    保存文件内容 + 保存文件描述信息
	 * 
	 * @param array $file 上传文件信息
	 * @param string $code 标识
	 * @return boolean|string md5
	 */
	public static function save_ext(array $file, $code = NULL)
	{
		// 重複的文件直接重用
		if ($file['exist'])
		{
			return $file['md5'];
		}
		
		// 构建文件描述信息
		$item = ORM::factory('File');
		$item->md5 = $file['md5'];
		$item->code = $code;
		$item->filename = $file['name'];
		$item->user_id = Auth::instance()->get_user_id();
		
		//检查目录
		$dir = $item->dir();
		
		if (!is_dir($dir))
		{ 
			//mkdir($dir);
			System::mkdirs($dir);
		}
		
		//保存文件
		if(parent::save($file, $item->filename, $dir))
		{
			//保存文件描述信息
			$item->save();
			return $item->md5;
		}
		
		return FALSE;
	}
	
	/**
	 * 删除单个文件
	 * @param string $md5
	 * @return bool
	 */
	public static function delete($md5)
	{
		// 找到文件描述信息
		//$file = ORM::factory('File')->where('md5', '=', $md5)->find();
		$file = ORM::factory('File', $md5);
		
		if ($file->loaded()) 
		{
			//删除物理文件
			$path = $file->path();
			
			if (is_file($path)) 
			{
				unlink($path);
			}
			
			//删除文件描述
			$file->delete(); 
			return TRUE;
		}
		
		return FALSE;
	}
	
	/**
	 * 删除多个文件
	 * @param string $code
	 * @return bool
	 */
	public static function delete_all($code)
	{
		// 找到文件描述信息
		$query = ORM::factory('File')->where('code', '=', $code);
		$deleter = clone $query;
		
		$files = $query->find_all();
	
		//删除物理文件
		foreach ($files as $file)
		{
			$path = $file->path();
				
			if (is_file($path))
			{
				unlink($path);
			}
		}
		
		//删除文件描述
		$deleter->delete_all();
		return TRUE;
	}
} 
