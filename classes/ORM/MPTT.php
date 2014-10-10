<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * 扩展mptt
 * 
 * @Package package_name ORM
 * @category mptt
 * @author shijianhang
 * @date Dec 17, 2013 11:07:47 AM 
 */
class ORM_MPTT extends Krishna_ORM_MPTT
{
	/**
	 * 属性设置: 如果parent_id为空字符串, 需转换为NULL
	 *    解决子类覆写filters()会导致parent_id的过滤器丢失,从而导致调用save_ext()会无法生成根结点
	 *    
	 * @see Krishna_ORM::set()
	 */
	public function set($column, $value)
	{
		if ($column === $this->parent_column)
		{
			$value = $this->empty_to_null($value);
		}
		
		return parent::set($column, $value);
	}
	
	/**
	 * 扩展save方法: 如果涉及到父节点的修改, 则需要移动或制造根节点
	 *
	 * @access public
	 * @param  Validation $validation Validation object
	 * @param  array $columns
	 * @return Model_MPTT|bool
	 */
	public function save_ext(Validation $validation = NULL, array $columns = NULL)
	{
		if ($this->loaded()) // update: maybe cause move
		{
			if(isset($this->_changed[$this->parent_column])) //If parent_id is changed, then move
			{
				$parent_id = $this->{$this->parent_column};
				
				// first recover its parent_id and save it
				$this->{$this->parent_column} = $this->_original_values[$this->parent_column];
				parent::save($validation);
				
				// then move it
				return $this->move_to($parent_id, 'last');
			}
			else //If not, just save
			{
				return parent::save($validation);
			}
		}
		else // create: maybe cause new_root
		{
			if (isset($this->{$this->parent_column})) // If it has a parent, just save
			{
				return $this->create_at($this->{$this->parent_column}, 'last', $validation);
			}
			else // If not(it has no parent), the it's root
			{
				return $this->make_root($validation);
			}
		}
		
	}
	
	/**
	 * Create a new term in the tree as a child of $parent
	 *
	 * - if `$location` is "first" or "last" the term will be the first or last child
	 * - if `$location` is an int, the term will be the next sibling of term with id $location
	 *
	 * @param   ORM_MPTT|integer  $parent    The parent
	 * @param   string|integer    $location  The location [Optional]
	 * @param  Validation $validation Validation object
	 * @return  Model_MPTT
	 * @throws  Krishna_Exception
	 */
	public function create_at($parent, $location = 'last', Validation $validation = NULL)
	{
		// Create the term as first child, last child, or as next sibling based on location
		if ($location == 'first')
		{
			$this->insert_as_first_child($parent, $validation);
		}
		else if ($location == 'last')
		{
			$this->insert_as_last_child($parent, $validation);
		}
		else
		{
			$target = ORM::factory($this->_object_name, (int) $location);
				
			if ( ! $target->loaded())
			{
				throw new Krishna_Exception("Could not create {$this->_object_name}, could not find target for
					insert_as_next_sibling id: " . (int) $location);
			}
	
				$this->insert_as_next_sibling($target, $validation);
		}
	
		return $this;
	}
	
	/**
	* Move the item to $target based on action
	*
	* @param   $target  integer  The target term id
	* @param   $action  string   The action to perform (before/after/first/last) after
	* @throws  Krishna_Exception
	*/
	public function move_to($target, $action = 'after')
	{
		// Find the target
		$target = ORM::factory($this->_object_name, (int) $target);

		// Make sure it exists
		if ( ! $target->loaded())
		{
			throw new Krishna_Exception("Could not move item, target item did not exist." . (int) $target->id);
		}
	
		switch ($action)
		{
			case 'before':
				$this->move_to_prev_sibling($target);
				break;
				
			case 'after':
				$this->move_to_next_sibling($target);
				break;
	
			case 'first':
				$this->move_to_first_child($target);
				break;
				
			case 'last':
				$this->move_to_last_child($target);
				break;
				
			default:
				throw new Krishna_Exception("Could not move item, action should be 'before', 'after', 'first' or 'last'.");
		}
	}
	
}
