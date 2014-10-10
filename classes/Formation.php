<?php defined('SYSPATH') OR die('No direct script access.');

class Formation 
{
	/** 模板 */
	protected $_template = 'form/basic';
	
	/** 元素集 */
	protected $_elements = array();
	
	/** 模型 */
	protected $_item;
	
	/** 标签 */
	protected $_labels;
	
	/**
	 * 返回表单元素
	 * @return array
	 */
	public static function form_elements()
	{
		return array(
			'input' => '文本框',
			'hidden' => '隐藏框',
			'password' => '密码框',
			'textarea' => '文本域',
			'file' => '文件框',
			'checkbox' => '复选框',
			'radio' => '单选框',
			'select' => '下拉框',
			'link' => '链接',
		);
	}
	
	/**
	 * 返回详情元素
	 * @return array
	 */
	public static function detail_elements()
	{
		return array(
			'enum' => '枚举',
			'fileinfo' => '文件信息',
			'link' => '链接',
		);
	}
	
	/**
	 * 构造函数
	 * @param Model_XXX $item
	 */
	public function __construct($item)
	{
		$this->_item = $item;
		$this->_labels = $item->labels();
	}
	
	/**
	 * 添加元素
	 * @param string $type
	 * @param string $field
	 * @param array $params
	 */
	public function add_elements($type, $field, array $params = array())
	{
		$label = Arr::get($this->_labels, $field); // 标签
		$value = isset($this->_item->$field) ? $this->_item->$field : NULL; // 值
		$this->_elements[$label] = static::element($type, $field, $value, $params); // 元素的html
	}

	/**
	 * 输出元素的html
	 * 
	 * @param string $type
	 * @param string $name
	 * @param string $value
	 * @param array $params
	 * @throws Krishna_Exception
	 * @return string
	 */
	public static function element($type, $name, $value, $params)
	{
		if (empty($type)) // 如果type为空,则直接输出值
		{
			return empty($params) ? $value : __($params, array(':value' => $value));//如果$params不为空， 则翻译他
		}
		
		switch ($type) // 输出type对应的控件
		{
			case 'input':
			case 'hidden':
			case 'password':
			case 'textarea':
				return Form::$type($name, $value, Arr::get($params, 'attrs'));
		
			case 'file':
				return Form::$type($name, Arr::get($params, 'attrs'));
	
			case 'checkbox':
			case 'radio':
				return Form::$type($name, TRUE, (bool) $value, Arr::get($params, 'attrs'));
	
			case 'select':
				$options = Arr::get($params, 'options');
				
				if (is_string($options)) // 构建枚举 
				{
					$options = ORM::factory('Enum', $options)->enarray();
				}
				
				return Form::select($name, (array) $options, $value, Arr::get($params, 'attrs')); // $params['options']可能为array/Enum, 因此对Enum类型需强制转换为array
			
			case 'enum':
				$options = Arr::get($params, 'options');
				
				if (is_string($options)) // 构建枚举
				{
					return ORM::factory('Enum', $options)->envalue($value);
				}
				
				return empty($value) ? NULL : Arr::get($options, $value);
			
			case 'link':
				$uri = Arr::get($params, 'uri');
				
				if ($value instanceof ORM)
				{
					$uri = empty($uri) ? 'admin/'.$value->object_name().'/show/'.$value->pk() : __($uri, array('id' => $value->pk()));
				}
				
				return empty($uri) ? $value : HTML::anchor($uri, $value, Arr::get($params, 'attrs'));
			
			case 'fileinfo':
				//$file = ORM::factory('File')->where('md5', '=', $value)->find();
				$file = ORM::factory('File', $value);
				return HTML::anchor($file->uri(), $file->name);
				
			//TODO: 支持date等复杂的控件,请参照cl4的控件体系 CL4_Form

			default:
				throw new Krishna_Exception('未知控件类型['.$type.']');
		}
	}
	
	/**
	 * element()扩展
	 * 
	 * @param string $field
	 * @param unknown $value
	 * @param array $options
	 * @return string
	 */
	public static function element_ext($field, $value, array $options = array())
	{
		$type = Arr::path($options, "$field.type");
		return static::element($type, $field, $value, Arr::path($options, $field));
	}
	
	/**
	 * 渲染
	 */
	public function render()
	{
		return (string)new View($this->_template, array('elements' => $this->_elements, 'item' => $this->_item));
	}
	
	/**
	 * 转换为字符串
	 * @return string
	 */
	public function __toString()
	{
		return $this->render();
	}
}
