<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * 枚举
 * 
 * @Package package_name 
 * @category enum
 * @author shijianhang
 * @date Dec 4, 2013 11:58:13 PM 
 *
 */
class Enum extends ArrayObject
{
	/**
	 * @var  array  enum instances
	 */
	public static $instances = array();
	
	/**
	 * get/create singleton enum instance
	 * 
	 * @param string $model 模型
	 * @param string $key 键的字段
	 * @param string $value 值的字段
	 * @param array $wheres 条件
	 * @return Enum
	 */
	public static function instance($model, $key = 'id', $value = 'name', array $wheres = array())
	{
		$name = "$model($key,$value)";
		
		if ( ! isset(Enum::$instances[$name]))
		{
			// Create a new Enum instance
			static::$instances[$name] = new Enum($model, $key, $value, $wheres);
		}
	
		return Enum::$instances[$name];
	}

	/**
	 * 构造函数
	 * @param string $model 模型
	 * @param string $key 键的字段
	 * @param string $value 值的字段
	 * @param array $wheres 条件
	 */
	public function __construct($model, $key, $value, array $wheres)
	{
		// 构建查询
		$query = ORM::factory($model);
		
		// 构建查询条件
		foreach ($wheres as $where)
		{
			$query->where($where[0], $where[1], $where[2]);
		}
		
		// 查询
		$data = $query->find_list($key, $value);
		
		parent::__construct($data, ArrayObject::ARRAY_AS_PROPS);
	}
	
}