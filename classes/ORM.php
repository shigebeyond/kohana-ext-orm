<?php defined ( 'SYSPATH' ) or die ( 'No direct script access.' );

/**
 * 扩展orm: 添加数据缓存+事件+校验方法
 * 
 * @Package  
 * @category orm 
 * @author shijianhang
 * @date Oct 23, 2013 11:03:35 PM 
 *
 */
class ORM extends Krishna_ORM
{
	/**
	 * 关联对象的查询与操作
	 */
	use Krishna_ORM_Relation;
	
	/**
	 * Auto-update columns for creator
	 * @var string
	 */
	protected $_creator_column = NULL;
	
	/**
	 * Auto-update columns for updater
	 * @var string
	 */
	protected $_updater_column = NULL;
	
	/******************************* 工厂 *******************************/
	/**
	 * 工厂方法
	 * @param string $model
	 * @param string $id
	 * @return ORM
	 */
	public static function factory($model, $id = NULL)
	{
		// 获得class名
		$class = 'Model_'.implode('_', array_map('ucfirst', explode('_', $model)));
		
		// 1 返回ORM对象
		if (class_exists($class)) 
		{
			if (is_subclass_of($class, 'ORM_Dat')) 
			{
				return new $class(Model_Dat_Object::instance($model), $id); // Dat_ORM
			} 
			else 
			{
				return new $class($id); // 普通ORM
			}
		}
		
		// 2 返回ORM_Dat_Base对象
		$dat_obj = Model_Dat_Object::instance($model); // 获得数据定义对象
		
		if ($dat_obj->treed) 
		{
			return new ORM_MPTT_Dat($dat_obj, $id); // 数据建模中的树形orm实现
		}
		else 
		{
			return new ORM_Dat($dat_obj, $id); // 数据建模中的orm实现
		}
		
	}
	
	/******************************* 缓存数据 *******************************/
	/** 空对象 */
	public static $null = NULL;
	
	/**
	 * 数据缓存
	 *   保留其他数据, 但又不创建属性, 如Tree类在节点中使用该属性来保存子结点
	 * 
	 * @var array
	 */
	protected $_data = array();
	
	/**
	 * 获得/设置数据缓存
	 *   类似于jquery的data(name, value)
	 *   
	 * @param string $name
	 * @param string $value
	 * @return multitype:
	 */
	public function &data($name, $value = NULL)
	{
		if ($value === NULL) 
		{
			if (isset($this->_data[$name])) 
			{
				return $this->_data[$name];
			}
			else
			{
				return static::$null;
			}
		}
		else
		{
			$this->_data[$name] = $value;
			return static::$null;
		}
	}
	
	/**
	 * 清空数据
	 * 
	 * @see Krishna_ORM::clear()
	 */
	public function clear()
	{
		$this->_data = $this->_original_related = array();
		
		return parent::clear();
	}
	
	/******************************* 序列化 *******************************/
	/** 标识字段 */
	protected $_name_field = NULL;
	
	/**
	 * 返回标识字段
	 */
	public function name_field()
	{
		return $this->_name_field;
	}
	
	/**
	 * 输出字符串
	 * @see Krishna_ORM::__toString()
	 */
	public function __toString()
	{
		if (empty($this->_name_field)) 
		{
			return json_encode($this->_object);
		}
		
		return (string) $this->{$this->_name_field};
	}
	
	/**
	 * 将orm的对象序列化为json
	 * @return string
	 */
	public function to_json()
	{
		return json_encode($this->as_array());
	}
	
	/**
	 * 从json中加载orm对象
	 * 
	 * @param string $json
	 * @return ORM
	 */
	public function from_json($json)
	{
		$values = json_decode($json, TRUE);
	
		// 根据id来查询对象
		if (isset($values[$this->_primary_key])) 
		{
			$this->where($this->_primary_key, '=', $values[$this->_primary_key])->find();
		}
	
		// 加载json数据
		return $this->values($values);
	}
	
	/******************************* 增删改方法中的事件 *******************************/
	/**
	 * 返回翻译的数组
	 */
	public function translated_array()
	{
		$object = array();
		
		foreach ($this->_object as $column => $value)
		{
			// Call __get for any user processing
			$object[":$column"] = $this->__get($column);
		}
		
		return $object;
	}
	
	/**
	 * 查询出下拉框的选项列表
	 *
	 * @param string $key 选项值/数组的key
	 * @param string $value 选项名/数组的value
	 * @return array
	 */
	public function find_list($key = NULL, $value = NULL)
	{
		if ($key === NULL)
		{
			$key = $this->_primary_key;
		}
	
		if ($value === NULL OR !preg_match("/[^_\w]/", $value)) 
		{
			return $this->find_all()->as_array($key, $value);
		}
		
		// 支持字符串翻译
		$result = array();
		
		foreach ($this->find_all() as $item)
		{
			$result[$item->$key] = __($value, $item->translated_array()); //翻译
		}
		
		return $result;
	}
	
	/**
	 * 创建: 添加before与after处理函数
	 * 
	 * @see Krishna_ORM::update()
	 */
	public function create(Validation $validation = NULL)
	{
		$this->before_create();
		
		if ($this->_creator_column AND !$this->changed($this->_creator_column)) //如果未赋值，则赋值当前用户
		{
			// Fill the creator column
			$this->{$this->_creator_column} = Auth::instance()->get_user_id();
		}
		
		$result = parent::create($validation);
		
		$this->after_create();
		
		return $result;
	}
	
	/**
	 * 更新: 添加before与after处理函数
	 * 
	 * @see Krishna_ORM::update()
	 */
	public function update(Validation $validation = NULL)
	{
		$this->before_update();
		
		if ($this->_updater_column AND !$this->changed($this->_creator_column)) //如果未赋值，则赋值当前用户
		{
			// Fill the updated column
			$this->{$this->_creator_column} = Auth::instance()->get_user_id();
		}
		
		$result = parent::update($validation);
		
		$this->after_update();
		
		return $result;
	}
	
	/**
	 * 删除: 添加before与after处理函数
	 * 
	 * @see Krishna_ORM::delete()
	 */
	public function delete()
	{
		$this->before_delete();
		
		if ( ! $this->_loaded)
			throw new Krishna_Exception('Cannot delete :model model because it is not loaded.', array(':model' => $this->_object_name));

		// Use primary key value
		$id = $this->pk();

		// Delete the object
		DB::delete($this->_table_name)
			->where($this->_primary_key, '=', $id)
			->execute($this->_db);

		//call after_delete() before the object clear
		$this->after_delete();
		
		return $this->clear();
	}
	
	/**
	 * 创建全部
	 * 
	 * @param array $items
	 * @param Validation $validation
	 * @return boolean
	 */
	public function create_all(array $items, Validation $validation = NULL)
	{
		if (empty($items))
		{
			return FALSE;
		}
		
		// 构建插入的sql
		$query = DB::insert($items[0]->_table_name)
			->columns(array_keys($items[0]->_object));
		
		foreach ($items as $item)
		{
			// Require model validation before saving
			if ( ! $item->_valid OR $validation)
			{
				$item->check($validation);
			}
				
			// 设置'创建时间'属性
			if (is_array($item->_created_column))
			{
				// Fill the created column
				$column = $item->_created_column['column'];
				$format = $item->_created_column['format'];
					
				$item->_object[$column] = ($format === TRUE) ? time() : date($format);
			}
			
			//添加插入sql的参数
			$query->values(array_values($item->_object));
		}
		
		//执行插入sql
		$result = $query->execute($items[0]->_db);
		$insert_id = $result[0];
		
		if (($row = $result[1]) !== count($items)) 
		{
			//批量插入未全部完成
			throw new Krishna_Exception('Batch insert incomplete because the number of rows affected not equals the count of items inserting!');
		}
		
		//更新items
		for ($i = 0; $i < $row; $i++)
		{
			// Load the insert id as the primary key 
			$items[$i]->_object[$items[$i]->_primary_key] = $items[$i]->_primary_key_value = $insert_id + $i;
			
			// Object is now loaded and saved
			$items[$i]->_loaded = $items[$i]->_saved = TRUE;
			
			// All changes have been saved
			$items[$i]->_changed = array();
			$items[$i]->_original_values = $items[$i]->_object;
		}
		
		return $items;
	}
	
	/**
	 * 删除全部
	 * 
	 * @throws Krishna_Exception
	 */
	public function delete_all()
	{
		if ($this->_loaded)
			throw new Krishna_Exception('Method delete_all() cannot be called on loaded objects');
	
		if ( ! empty($this->_load_with))
		{
			foreach ($this->_load_with as $alias)
			{
				// Bind auto relationships
				$this->with($alias);
			}
		}
	
		$this->_build(Database::DELETE);
	
		$this->_db_builder->execute($this->_db);
	}
	
	public function before_create(){}
	
	public function after_create(){}
	
	public function before_update(){}
	
	public function after_update(){}
	
	public function before_delete(){}
	
	public function after_delete(){}
	
	/******************************* model内的valid/filter方法 *******************************/
	/**
	 * Checks whether a column value is unique.
	 * Excludes itself if loaded.
	 *
	 * @param   string   $field  the field to check for uniqueness
	 * @param   mixed    $value  the value to check for uniqueness
	 * @param   array    $other_fields  other field unite with the field to check for uniqueness
	 * @return  bool     whteher the value is unique
	 */
	public function unique($field, $value, array $other_fields = NULL)
	{
		$query = static::factory($this->object_name())
			->where($field, '=', $value);
	
		if($other_fields !== NULL)
		{
			foreach ($other_fields as $other_field)
			{
				$query->where($other_field, '=', $this->$other_field);
			}
		}
	
		$model = $query->find();
	
		if ($this->loaded())
		{
			return ( ! ($model->loaded() AND $model->pk() != $this->pk()));
		}
	
		return ( ! $model->loaded());
	}
	
	/**
	 * 将空字符串转换为NULL
	 * @param string $value
	 * @return NULL|string
	 */
	public function empty_to_null($value)
	{
		return empty($value) ? NULL : $value;
	}
	
	/******************************* 输出表单/详情 *******************************/
	/**
	 * 返回表单选项
	 * 
	 * @return array
	 */
	public function form_options()
	{
		return array();
	}
	
	/**
	 * 返回详情选项
	 * 
	 * @return array
	 */
	public function detail_options()
	{
		return array();
	}
	
	
	/**
	 * 获得指定字段的关联枚举的所有值
	 *
	 * @param string $field
	 * @return array
	 */
	public function enum_array($field)
	{
		$option = Arr::get($this->detail_options(), $field, FALSE);
		
		if ($option AND $option['type'] === 'enum')
		{
			return $option['options'];
		}
		
		return NULL;
	}
	
	/**
	 * 获得指定字段值对应的枚举值
	 *
	 * @param string $field
	 * @return string
	 */
	public function enum_value($field)
	{
		return Arr::get($this->enum_array($field), $this->$field);
	}
	
	/**
	 * 获得表单/详情
	 * 
	 * @param array $fields 输出的字段
	 * @param array $options 输出选项
	 * @return Formation
	 */
	protected function _form(array $fields = NULL, array $options = array())
	{
		// 设置默认的输出字段
		if ($fields === NULL)
		{
			$fields = array_keys($options);
		}
	
		// 构建form
		$form = new Formation($this);
	
		// 添加form元素
		foreach ($fields as $field)
		{
			$form->add_elements($options[$field]['type'], $field, $options[$field]);
		}
	
		return $form;
	}
	
	/**
	 * 输出表单
	 * 
	 * @param array $fields 输出的字段
	 * @return Formation
	 */
	public function form(array $fields = NULL)
	{
		return $this->_form($fields, $this->form_options());
	}
	
	/**
	 * 输出详情
	 * 
	 * @param array $fields 输出的字段
	 * @return Formation
	 */
	public function detail(array $fields = NULL)
	{
		return $this->_form($fields, $this->detail_options());
	}
	
	/**
	 * 输出元素
	 * 
	 * @param string $field 输出的字段
	 * @param array $options
	 * @param string $name 控件名
	 * @return string
	 */
	public function element($field, array $options = array(), $name = NULL)
	{
		$type = $field === $this->primary_key() ? 'hidden' : Arr::path($options, "$field.type");
		$name = $name === NULL ? $field : $name;
		return Formation::element($type, $name, $this->$field, Arr::path($options, $field));
	}
	
	/**
	 * 判断字段能否为空
	 *     当使用orm::values(), 如果$values没有指定字段的值, 则根据该函数来确定是否设置当前字段为NULL
	 *     注意由此会导致isset()返回true, 因此为减少误会, 只有表单元素为checkbox的字段才会为NULL
	 *     
	 * @param string $column
	 * @return bool
	 */
	public function is_nullable($column)
	{
		return Arr::path($this->form_options(), "$column.type") === 'checkbox';
	}
	
	/**
	 * 获得字段
	 * 
	 * @param bool $pked 是否包含主键
	 * @param array $relations 关联关系 <关联关系名, 是否递归>
	 * @return array
	 */
	public function fields($pked = FALSE, array $relations = NULL)
	{
		$fields = $this->_table_columns;
		
		// 没有主键
		if (!$pked)
		{
			unset($fields[$this->_primary_key]);
		}
		
		$fields = array_keys($fields);
		
		// 包含关联对象的字段
		if (! empty($relations)) 
		{
			foreach ($relations as $relation => $recur) // 关联关系 => 是否递归
			{
				$relation = '_'.$relation;
				
				foreach ($this->$relation as $alias => $options)
				{
					if ($recur)
					{
						// 递归获得关联对象的字段
						$fields[$alias] = static::factory($options['model'])->fields();
					}
					else 
					{
						$fields[] = $alias;
					}
				}
			}
		}
		
		return $fields;
	}
	
	/******************************* 文件上传 *******************************/
	/**
	 * 是否有文件上传的控件
	 * @param array $fields
	 * @return boolean
	 */
	public function has_file_uploaded(array $fields = NULL)
	{
		if ($fields === NULL)
		{
			$fields = array_keys($this->_table_columns);
		}
		
		foreach ($fields as $key => $field)
		{
			if (is_string($key))
			{
				// 不考虑关联对象的文件上传
				/* list($relation, $config) = $this->relation($key);//获得关联关系
				$related = ORM::factory($config['model']);//获得关联的model
		
				if ($related->has_file_input($field)) // 递归调用
				{
					return TRUE;
				} */
			}
			elseif (Arr::path($this->form_options(), "$field.type") === 'file') // 判断该字段的控件类型是否是文件上传控件
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	/**
	* 获得关联文件的属性
	* @param array $fields
	* @return array
	*/
	protected function _get_file_uploaded(array $fields = NULL)
	{
		if ($fields === NULL)
		{
			$fields = array_keys($this->_table_columns);
		}
		
		$result = array();
	
		foreach ($fields as $key => $field)
		{
			if (is_string($key))
			{
				// 不考虑关联对象的文件上传
			}
			elseif (Arr::path($this->form_options(), "$field.type") === 'file') // 判断该字段的控件类型是否是文件上传控件
			{
				$result[] = $field;
			}
		}
	
		return $result;
	}
	
	/**
	 * 尝试上传单个文件并设置相关属性
	 * @param array $expected
	 * @return Validation 校验器
	 */
	public function try_upload_file($expected)
	{
		//如果有文件则上传文件
		$fields = $this->_get_file_uploaded($expected);
		
		if (empty($fields)) 
		{
			return NULL;
		}
		
		//保存结果的校验器
		$result = NULL;
		
		//1 校验上传文件
		foreach ($fields as $field)
		{
			$label = Arr::get($this->labels(), $field);//获得字段标签
			$options = Arr::get($this->form_options(), $field);// 获得文件上传的选项
			$expected_exts = Arr::get($options, 'expected_exts');// 获得文件类型限制
			$size = Arr::get($options, 'size');// 获得文件大小限制
			
			//1.1 获得校验器
			$validation = Upload::validation($_FILES, $field, $label, $expected_exts, $size);
			 
			//1.2 校验
			if (!$validation->check())
			{
				$result = Validation::merge($result, $validation);//记录失败的校验器
			}
		}
		
		//2 保存上传文件
		foreach ($fields as $field)
		{
			if ($result === NULL OR $result->check())
			{
				//2.1 保存新文件
				$this->$field = Upload::save_ext($_FILES[$field], $this->object_name().'/'.$this->pk());
				
				//2.2 删除旧文件
				if (!empty($this->_original_values[$field])) 
				{
					Upload::delete($this->_original_values[$field]);
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * 上传多个文件
	 * 
	 * @param string $field 上传文件的字段
	 * @return boolean|Validation
	 */
	public function upload_files($field = 'files')
	{
		if (($multi = Upload_Multi::factory($field)) === FALSE) 
		{
			throw new Virtual_Validation_Exception("创建多文件上传的处理器失败");
		}
		
		//保存结果的校验器
		$result = NULL;
		
		$label = Arr::get($this->labels(), $field);//获得字段标签
		$options = Arr::get($this->form_options(), $field);// 获得文件上传的选项
		$expected_exts = Arr::get($options, 'expected_exts');// 获得文件类型限制
		$size = Arr::get($options, 'size');// 获得文件大小限制
			
		//遍历多个上传的文件
		for ($i = 0; $i < $multi->count_files(); $i++)
		{
			//1.1 获得校验器
			$validation = $multi->validation($i, $label, $expected_exts, $size);
			
			//1.2 校验
			if ($validation->check())
			{
				//2 保存上传文件
				$multi->save_ext($i, $this->file_code());
			}
			else
			{
				//记录失败的校验器
				$result = Validation::merge($result, $validation);
			}
		}
		
		// 抛出校验的异常
		if ($result) 
		{
			throw new Virtual_Validation_Exception($result->errors());
		}
	}
	
	/**
	 * 获得文件代码
	 * @return string
	 */
	public function file_code() 
	{
		return $this->object_name().'/'.$this->pk();
	}
	
	/**
	 * 获得关联的文件
	 * @return array
	 */
	public function files()
	{
		return ORM::factory('File')
			->where('code', '=', $this->file_code())
			->find_all();
	}
}