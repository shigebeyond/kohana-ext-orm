<?php defined ( 'SYSPATH' ) or die ( 'No direct script access.' );

/**
 * 扩展orm: 关联对象的查询与操作
 *   1 关联对象的查询
 *     find_all_include(array $columns = NULL) 查询全部: 连带查询关联的对象
 *   
 *   2 关联对象的保存
 *     save_include(Validation $validation = NULL, array $columns = NULL) 
 *   
 *   3 关联对象的删除
 *     delete_include(array $columns = NULL) 
 * 
 * @Package  Krishna/ORM 
 * @category orm 
 * @author shijianhang
 * @date Oct 23, 2013 11:03:35 PM 
 *
 */
trait Krishna_ORM_Relation  
{
	/**
	 * 原始的关联对象
	 *
	 * @var array
	 */
	protected $_original_related = array();
	
	/**
	 *  是否缓存has_many的关联属性 
	 *  
	 *  @var bool
	 */
	protected $_has_many_cached = TRUE;
	
	/**
	 * 获得关联关系配置
	 * 
	 * @param string $column
	 * @return boolean|array 关联关系 + 配置
	 */
	public function relation($column)
	{
		$relations = array('_has_one', '_belongs_to', '_has_many');
		
		foreach ($relations as $relation)
		{
			if (isset($this->{$relation}[$column])) 
			{
				return array(substr($relation, 1), $this->{$relation}[$column]); // 关联关系 + 配置
			}
		}
		
		return FALSE;
	}
	
	/**
	 * 獲得多個關聯關係配置
	 * 
	 * @param array $types 关联关系類型
	 * @return array
	 */
	public function relations(array $types = NULL)
	{
		if ($types === NULL)
		{
			$types = array('belongs_to', 'has_one', 'has_many');
		}
		
		$result = array();
		
		foreach ($types as $type) 
		{
			$type = '_'.$type;
			$result = array_merge($result, $this->$type);
		}
		
		return $result;
	}
	
	/**
	 * 过滤关联对象的字段
	 * 
	 * @param array $fields
	 * @return array
	 */
	public function filter_relations(array $fields)
	{
		$result = array();
		
		foreach ($fields as $field)
		{
			$relation = $this->relation($field);
			
			if ($relation) 
			{
				$result[$field] = $relation;
			}
		}
		
		return $result;
	}
	
	/******************************* 关联对象的查询 *******************************/
	/**
	 * 查询全部: 连带查询关联的对象
	 *    TODO: 由于查询一对一的关联对象时也要执行sql,因此他在这方面比Krishna_ORM效率要慢,他可以考虑结合Krishna_ORM::with($target_path)来使用
	 *
	 * @param array $columns 要连带查询的关联对象列
	 * @return array
	 */
	public function find_all_include(array $columns = NULL)
	{
		// 获得所有对象
		$items = $this->find_all ()->as_array();//无as_array()则每次从结果集中取数（导致每次访问$items(如遍历)都会重新取数，从而导致设置的$items元素的属性都丢掉）, 有as_array()则一次性取数, 而不会多次取数
	
		if (empty ($items) OR empty($columns) OR empty($this->_belongs_to) AND empty($this->_has_one) AND empty($this->_has_many))
		{
			return $items;
		}
	
		//遍历关联的列
		$relations = array('_has_one', '_belongs_to', '_has_many');
		
		foreach ($columns as $column)
		{
			// 获得列对应的关联对象
			foreach ($relations as $relation)
			{
				if (isset($this->{$relation}[$column]))
				{
					$includer = "_include{$relation}";
					$this->$includer($column, $items);
				}
			}
		}
	
		return $items;
	}
	
	/**
	 * 获得belongs_to的关联对象
	 * @param column
	 * @param items
	 * @param related_cache
	 */
	private function _include_belongs_to($column, $items)
	{
		//获得关联对象的模型
		$model = $this->_related($column);
	
		//关联关系：本对象的外键对应关联对象的主键
		//获得外键列（关联对象的主键）
		$col = $model->_object_name.'.'.$model->_primary_key;
	
		//获得外键值（本对象的外键值）
		$foreign_key = $this->_belongs_to[$column]['foreign_key'];
		$vals = Arr::pluck_field($items, $foreign_key, TRUE);
	
		// 查询关联对象
		$related_items = NULL;
	
		if ($vals !== NULL)
		{
			$related_items = $model->where($col, 'in', $vals)->find_all()->as_array($model->_primary_key);
		}
	
		//保存关联对象
		if (!empty($related_items))
		{
			//保存关联对象
			foreach ($items as $item)
			{
				$fk = $item->_object[$foreign_key];
	
				if (isset($related_items[$fk]))
				{
					$item->_related[$column] = $related_items[$fk];
				}
			}
		}
	
	}
	
	/**
	 * 获得has_one的关联对象
	 * @param column
	 * @param items
	 */
	private function _include_has_one($column, $items) 
	{
		//获得关联对象的模型
		$model = $this->_related($column);
	
		//关联关系：本对象的主键=关联对象的外键
		//获得外键列（关联对象的外键）
		$foreign_key = $this->_has_one[$column]['foreign_key'];
		$col = $model->_object_name.'.'.$foreign_key;
	
		//获得外键值（本对象的主键值）
		$vals = Arr::pluck_field($items, $this->_primary_key, TRUE);
	
		// 查询关联对象
		$related_items = $model->where($col, 'in', $vals)->find_all()->as_array($foreign_key);
	
		//保存关联对象
		if (!empty($related_items))
		{
			//保存关联对象
			foreach ($items as $item)
			{
				if (isset($related_items[$item->pk()]))
				{
					$item->_related[$column] = $related_items[$item->pk()];
				}
			}
		}
	}
	
	/**
	 * 获得has_many的关联对象
	 * @param column
	 * @param items
	 */
	private function _include_has_many($column, $items) 
	{
		//获得关联对象的模型
		$model = static::factory($this->_has_many[$column]['model']);
	
		$foreign_key = $this->_has_many[$column]['foreign_key'];
		if (isset($this->_has_many[$column]['through']))//有中间对象
		{
			// 获得中间对象的模型
			$through = $this->_has_many[$column]['through'];
	
			// 关联关系：中间对象的外键(far_key)=关联对象的主键
			$join_col1 = $through.'.'.$this->_has_many[$column]['far_key'];
			$join_col2 = $model->_object_name.'.'.$model->_primary_key;
	
			$model->join($through)->on($join_col1, '=', $join_col2);
	
			// 关联关系：中间对象的外键(foreign_key)=本对象的主键
			// 获得外键列（中间对象的外键）
			$col = $through.'.'.$foreign_key;
	
			// 获得外键值（本对象的主键值）
			$vals = Arr::pluck_field($items, $this->_primary_key, TRUE);
	
			// 查询关联对象属性+(中间对象的外键/本对象主键)
			$model->select($model->_object_name.'.*', array($col, $foreign_key));
		}
		else//无中间对象
		{
			//关联关系：本对象的主键=关联对象的外键
			//获得外键列（关联对象的外键）
			$col = $model->_object_name.'.'.$foreign_key;
	
			//获得外键值（本对象的主键值）
			$vals = Arr::pluck_field($items, $this->_primary_key, TRUE);
		}
	
		// 查询关联对象
		$related_items = $model->where($col, 'in', $vals)->find_all();
	
		//保存关联对象
		if (!empty($related_items))
		{
			//缓存关联对象
			$related_cache = array();
				
			foreach ($related_items as $related_item)
			{
				$fk = $related_item->{$foreign_key};
	
				if (!isset($related_cache[$fk]))
				{
					$related_cache[$fk] = array();
				}
	
				$related_cache[$fk][] = $related_item;
			}
	
			//保存关联对象
			foreach ($items as $item)
			{
				if (isset($related_cache[$item->pk()]))
				{
					$item->_related[$column] = $related_cache[$item->pk()];
				}
			}
		}
	}
	
	/**
	 * 获得属性: 缓存has_many关联模型的查询结果
	 *
	 * @see Krishna_ORM::get()
	 */
	public function get($column)
	{
		$result = parent::get($column);
	
		// 缓存has_many关联模型的查询结果
		if ($this->_has_many_cached AND isset($this->_has_many[$column]) AND !isset($this->_related[$column]))
		{
			$this->_related[$column] = $result = $result->find_all()->as_array($result->_primary_key);
		}
	
		return $result;
	}
	
	/**
	 * 获得关联对象的个数: 从缓存的关联对象中获取
	 *
	 *   影响: 有两个方法的逻辑受到影响, 因此他们内部都是采用 ORM::count_relations($alias, $far_keys) 来实现的
	 *   1 ORM::has($alias, $far_keys)
	 *   2 ORM::has_any($alias, $far_keys)
	 *
	 *   作用: 优化ORM::has($alias, $far_keys) 与 ORM::has_any($alias, $far_keys) 的频繁调用
	 *   如在用户的设置角色页面中,需要频繁判断用户是否拥有某角色: $user->has('roles', $role->id)
	 *
	 * @see Krishna_ORM::count_relations()
	 */
	public function count_relations($alias, $far_keys = NULL)
	{
		if (! $this->_loaded)
		{
			return 0;
		}
	
		// 获得缓存的关联对象
		$related_items = $this->$alias;
	
		if ($far_keys === NULL)
		{
			// 直接返回缓存的关联对象的个数
			return count($related_items);
		}
	
		$far_keys = ($far_keys instanceof ORM) ? $far_keys->pk() : $far_keys;
	
		// We need an array to simplify the logic
		$far_keys = (array) $far_keys;
	
		// 如果far_keys为空,则无可检查,直接返回0
		if ( ! $far_keys)
			return 0;
	
		// 在缓存的关联对象中搜索匹配far_keys的记录个数
		$count = 0;
	
		foreach ($far_keys as $far_key)
		{
			if (isset($related_items[$far_key]))
			{
				$count++;
			}
		}
	
		return $count;
	}
	
	/******************************* 关联对象的保存: 只保存 has_one/has_many 的关联对象 *******************************/
	/**
	 * 判断字段能否为空
	 *     当使用orm::values(), 如果$values没有指定字段的值, 则根据该函数来确定是否设置当前字段为NULL
	 *     注意由此会导致isset()返回true
	 * @param string $column
	 * @return bool
	 */
	public function is_nullable($column)
	{
		return FALSE;
	}
	
	/**
	 * 加载对象的属性: 连带加载关联对象的属性
	 *   1 过滤关联对象
	 *   只加载 has_one/has_many 的关联对象(的属性),因为本对象是主表,关联对象是从表,主表能改变从表的数据
	 *   不加载 belong_to 的关联对象(的属性),因为本对象是从表,关联对象是主表,而从表不能改变主表的数据
	 *
	 * @see Krishna_ORM::values()
	 */
	public function values(array $values, array $expected = NULL)
	{
		// Default to expecting everything except the primary key
		if ($expected === NULL)
		{
			$expected = array_keys($this->_table_columns);
	
			// Don't set the primary key by default
			unset($values[$this->_primary_key]);
		}
	
		foreach ($expected as $key => $column)
		{
			$related_expected = NULL;
				
			if (is_string($key))
			{
				$related_expected = $column; // 关联对象的$expected
				$column = $key;
			}
				
			// isset() fails when the value is NULL (we want it to pass)
			if ( ! array_key_exists($column, $values))
			{
				if ($this->is_nullable($column)) // 如果字段满足is_nullable(), 则字段值可以为空 
				{
					$values[$column] = NULL;
				}
				elseif (isset($this->_has_many[$column])) // 如果字段是'有多个'的关联对象字段, 则字段值为空数组
				{
					$values[$column] = array();
				}
				else
				{
					continue;
				}
			}
				
			if (isset($this->_has_one[$column])) // 加载has_one的关联对象的属性
			{
				$model = static::factory($this->_has_one[$column]['model']);
	
				if (!empty($values[$column][$model->_primary_key])) 
				{
					$related = $this->$column; //已存在的对象: 假定$this->$column与$related_value有一致的id
				}
				else //创建
				{
					$related = static::factory($this->_has_one[$column]['model']);//新对象
				}
	
				$related->values($values[$column], $related_expected); //加载属性
			}
			elseif (isset($this->_has_many[$column])) // 加载has_many的关联对象的属性
			{
				$related = array();
				
				if ($this->_has_many_cached) 
				{
					$model = static::factory($this->_has_many[$column]['model']);
					$old_related = $this->_original_related[$column] = $this->$column;
				}
				else
				{
					$model = $this->$column;
					$old_related = $this->_original_related[$column] = $model->find_all()->as_array($model->_primary_key); // 缓存has_many关联模型的原始数据
				}
	
				foreach ($values[$column] as $i => $related_value)
				{
					if ($old_related AND !empty($related_value[$model->_primary_key])) //更新: 待更新的对象必须是已加载(loaded=true)
					{
						$related[$i] = $old_related[$related_value[$model->_primary_key]]; //已加载的对象: 假定$this->$column的子项与$related_value的子项有一致的id
					}
					else //创建
					{
						$related[$i] = static::factory($this->_has_many[$column]['model']);//新对象
					}
						
					$related[$i]->values($related_value, $related_expected);//加载属性
				}
			}
				
			// Update the column
			$this->$column = isset($related) ? $related : $values[$column];
		}
	
		return $this;
	}
	
	/**
	 * 设置属性: 设置(关联对象的)属性
	 *   1 过滤关联对象
	 *   只设置( has_one/has_many 的关联对象的)属性,因为本对象是主表,关联对象是从表,主表能改变从表的数据
	 *   不设置( belong_to 的关联对象的)属性,因为本对象是从表,关联对象是主表,而从表不能改变主表的数据
	 *
	 * @see Krishna_ORM::set()
	 */
	public function set($column, $value)
	{
		try {
			return parent::set($column, $value);
		}
		catch (Krishna_Exception $e)
		{
			// 设置(关联对象的)属性
			if (isset($this->_has_one[$column])  // 设置(has_one的关联对象的)属性: 假定$value是ORM对象
			OR isset($this->_has_many[$column]))  // 设置(has_many的关联对象的)属性: 假定$value是数组
			{
				$this->_related[$column] = $value;
				return $this;
			}
				
			throw $e;
		}
	
	}
	
	/**
	 * 保存: 连带保存关联对象
	 *   1 过滤关联对象
	 *   只保存 has_one/has_many 的关联对象,因为本对象是主表,关联对象是从表,主表能改变从表的数据
	 *   不保存 belong_to 的关联对象,因为本对象是从表,关联对象是主表,而从表不能改变主表的数据
	 *   
	 *   2 设置关联对象的外键:
	 *     对创建的情况,只有等本对象创建之后才能给关联对象设置外键；
	 *     而当本对象保存之后已经分不清楚是创建还是修改,同时关联对象的外键是不可信的,因此必须要对关联对象设置外键
	 *
	 * @param Validation $validation
	 * @param array $columns
	 */
	public function save_include(Validation $validation = NULL, array $columns = NULL)
	{
		// 开始事务
		$this->_db->begin();
		
		try
		{
			// 保存本对象
			$this->save($validation);
		
			if (!empty($columns))
			{
				// 保存关联对象
				foreach ($columns as $column)
				{
					if (isset($this->_has_one[$column])) //保存has_one的关联对象
					{
						// 假定$this->$column是ORM对象
						$this->$column->set($this->_has_one[$column]['foreign_key'], $this->pk()) //更新关联对象的外键
							->save();
					}
					elseif (isset($this->_has_many[$column])) //保存has_many的关联对象
					{
						// 假定$this->$column是ORM的数组
						$this->_save_has_many($column);
					}
				}
			}
			
			$this->_db->commit();// 提交事务
			return $this;
		}
		catch (ORM_Validation_Exception $e)
		{
			$this->_db->rollback(); // 回滚事务
			throw $e;
		}
	}
	
	/**
	 * 保存has_many的关联对象
	 *    1 设置关联对象的外键:
	 *      对创建的情况,只有等本对象创建之后才能给关联对象设置外键；
	 *      而当本对象保存之后已经分不清楚是创建还是修改,同时关联对象的外键是不可信的,因此必须要对关联对象设置外键
	 *
	 * @param string $column
	 * @param array $related
	 */
	protected function _save_has_many($column)
	{
		$created = array(); //待创建的关联对象
		$updated = array(); //待更新的关联对象
		$deleted = array(); //待删除的关联对象
	
		// 1 分辨待创建/更新项
		foreach ($this->$column as $related_item)
		{
			$related_item->set($this->_has_many[$column]['foreign_key'], $this->pk()); //更新关联对象的外键
				
			if ($related_item->_loaded) // 如果关联对象已加载,则认为是更新的
			{
				$updated[$related_item->pk()] = $related_item;
			}
			else // 否则, 认为是创建的
			{
				$created[] = $related_item;
			}
				
		}
	
		// 2 分辨待删除项
		if (isset($this->_original_related[$column]))
		{
			foreach ($this->_original_related[$column] as $old_related_item) //如果旧关联对象有+新关联对象没有的情况,则删除该旧关联对象
			{
				if (! isset($updated[$old_related_item->pk()]))
				{
					$deleted[$old_related_item->pk()] = $old_related_item;
				}
			}
		}
	
		// 3 批量创建
		if (! empty($created)) 
		{
			$this->create_all($created);
		}
	
		// 4 逐个更新
		foreach ($updated as $updated_item)
		{
			$updated_item->update(); //ORM::update()方法中做了优化: 只有属性变化的对象才会执行更新sql
		}
	
		// 5 批量删除
		if (! empty($deleted))
		{
			$model = static::factory($this->_has_many[$column]['model']);
			$model->where($model->_primary_key, 'in', array_keys($deleted))
				->delete_all();
		}
	}
	
	/******************************* 关联对象的删除: 只删除 has_one/has_many 的关联对象 *******************************/
	
	/**
	 * 删除: 连带删除关联对象
	 *   1 过滤关联对象
	 *   只删除 has_one/has_many 的关联对象,因为本对象是主表,关联对象是从表,主表能改变从表的数据
	 *   不删除 belong_to 的关联对象,因为本对象是从表,关联对象是主表,而从表不能改变主表的数据
	 * 
	 * @param array $columns
	 * @return ORM
	 */
	public function delete_include(array $columns = NULL)
	{
		// 开始事务
		$this->_db->begin();
		
		try
		{
			//删除关联对象
			if (! empty($columns))
			{
				foreach ($columns as $column)
				{
					if (isset($this->_has_one[$column])) //删除has_one的关联对象
					{
						if ($this->$column->loaded()) 
						{
							$this->$column->delete();
						}
					}
					elseif (isset($this->_has_many[$column])) //删除has_many的关联对象
					{
						//批量删除
						$model = ORM::factory($this->_has_many[$column]['model'])
							->where($this->_has_many[$column]['foreign_key'], '=', $this->pk())
							->delete_all();
					}
				}
			}
			
			//删除本对象
			$this->delete();
			$this->_db->commit();// 提交事务
			return $this;
		}
		catch (ORM_Validation_Exception $e)
		{
			$this->_db->rollback();// 回滚事务
			throw $e;
		}
	}
	
}