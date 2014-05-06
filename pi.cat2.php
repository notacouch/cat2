<?php

$plugin_info = array(
  'pi_name' => 'Cat2',
  'pi_version' =>'1.1.11',
  'pi_author' =>'Mark Croxton',
  'pi_author_url' => 'http://www.hallmark-design.co.uk/',
  'pi_description' => 'Convert between category name, category id and category url title',
  'pi_usage' => Cat2::usage()
  );

class Cat2 {
	
	public $return_data = '';
	public $category_url_title;
	public $category_id;
	public $category_name;
	public $category_group;
	public $field_short_name;
	public $field_id;
	public $default_value;
	public $site;
	private $_debug;
	
	/** 
	 * Constructor
	 *
	 * @access public
	 * @return void
	 */
	function Cat2() 
	{
		$this->site = ee()->config->item('site_id');
		$cat_id = explode('|', ee()->TMPL->fetch_param('category_id', NULL));

		
		// register parameters
		$this->category_url_title = strtolower(ee()->TMPL->fetch_param('category_url_title', ''));
		$this->category_name      = strtolower(ee()->TMPL->fetch_param('category_name', ''));
		$this->category_id        = preg_replace("/[^0-9]/", '', $cat_id[0]);
		$this->category_group     = preg_replace("/[^0-9]/", '', ee()->TMPL->fetch_param('category_group', '0')); // Can have same category_url_titles in different groups
		$this->field_short_name   = strtolower(ee()->TMPL->fetch_param('field_short_name', ''));
		$this->field_id           = preg_replace("/[^0-9]/", '', ee()->TMPL->fetch_param('field_id', NULL));
		$this->default_value      = ee()->TMPL->fetch_param('default_value', '');
		$this->_debug             = (bool) preg_match('/1|on|yes|y/i', ee()->TMPL->fetch_param('debug'));	
	}
	
	/** 
	 * exp:cat2:id
	 *
	 * @access public
	 * @return string
	 */
	function id() 
	{
		if (empty($this->category_url_title) && empty($this->category_name))
		{
			// parameter required, fail gracefully
			if ($this->_debug)
			{
				show_error(__CLASS__.' error: one of the following parameters is required: category_url_title OR category_name.');
			}
			return;
		}
		
		if (!empty($this->category_url_title))
		{
			$key 	= "cat_url_title";
			$value 	= $this->category_url_title;
		}
		else
		{
			$key 	= "cat_name";
			$value	 = $this->category_name;
		}
		
		return $this->cat_query('cat_id', $key, $value);
	}
	
	
		
	/** 
	 * exp:cat2:name
	 *
	 * @access public
	 * @return string
	 */
	function name() 
	{
		if (empty($this->category_url_title) && empty($this->category_id))
		{
			// parameter required, fail gracefully
			if ($this->_debug)
			{
				show_error(__CLASS__.' error: one of the following parameters is required: category_url_title OR category_id.');
			}
			return;
		}
		
		if ( ! empty($this->category_url_title))
		{
			$key 	= "cat_url_title";
			$value 	=  $this->category_url_title;
		}
		else
		{
			$key 	= "cat_id";
			$value	 = $this->category_id;
		}
		
		return $this->cat_query('cat_name', $key, $value);
	}
	
	/** 
	 * exp:cat2:url_title
	 *
	 * @access public
	 * @return string
	 */
	function url_title() 
	{
		if (empty($this->category_name) && empty($this->category_id))
		{
			// parameter required, fail gracefully
			if ($this->_debug)
			{
				show_error(__CLASS__.' error: one of the following parameters is required: category_name OR category_id.');
			}
			return;
		}
		
		if ( ! empty($this->category_name))
		{
			$key 	= "cat_name";
			$value 	=  $this->category_name;
		}
		else
		{
			$key 	= "cat_id";
			$value	 = $this->category_id;
		}
		
		return $this->cat_query('cat_url_title', $key, $value);
	}
	
	/** 
	 * The main query
	 *
	 * @access public
	 * @param string $col 	the column we want to get
	 * @param string $key  	the column we're searching
	 * @param string $value the column value we're searching for
	 * @return string
	 */
	protected function cat_query($col, $key, $value)
	{
	  $cache_key     = $this->cache_key($col, $key, $value);
	  $cached_result = ee()->cache->get($cache_key);
		if (empty($cached_result))
		{
			// query
			ee()->db->select($col);
			ee()->db->from('exp_categories');
			ee()->db->where('site_id', $this->site);
		
			if ($key == 'cat_id')
			{
				ee()->db->where($key, $value);
			}
			else
			{
				ee()->db->where("LOWER({$key})", $value);
			}
			
			if ( ! empty($this->category_group))
			{
				ee()->db->where('group_id', $this->category_group);
			}
			
			// run the query
			$results = ee()->db->get();
			
			if ($results->num_rows() > 0) 
			{
			  $cached_result = $results->row($col);
				ee()->cache->save($cache_key, $cached_result);
			}
			else
			{
				// fail gracefully
				//ee()->session->cache[__CLASS__][$col][$value] = '';
				// How to do this with new Cache class?
				
				if ($this->_debug)
				{
					show_error(__CLASS__.' error: category not found.');
				}
			}
		}
		
		// is this a tag pair?
		$tagdata = ee()->TMPL->tagdata;
	
		if ( ! empty($tagdata))
		{
		  switch ($col)
		  {
		    case 'cat_name':
		      $tmpl_variable = 'category_name';
		      break;
		    case 'cat_url_title':
		      $tmpl_variable = 'category_url_title';
		      break;
		    case 'cat_id':
  		  default:
  		    $tmpl_variable = 'category_id';
          break;
		  }
			return ee()->TMPL->parse_variables_row(
							$tagdata,
							array($tmpl_variable => $cached_result)
					);
		}
		else
		{
			// output direct
			return $cached_result;
		}
	}
	
	/** 
	 * exp:cat2:field_value
	 *
	 * @access public
	 * @return string
	 */
	function field_value() 
	{
		if (empty($this->field_short_name) && empty($this->field_id))
		{
			// parameter required, fail gracefully
			if ($this->_debug)
			{
				show_error(__CLASS__.' error: one of the following parameters is required: field_short_name OR field_id.');
			}
			return $this->default_value;
		}
		
		// The ultimate query is:
		// SELECT field_id_{field_id} FROM exp_category_field_data WHERE cat_id = {cat_id} AND site_id = {site_id}
		// But we generally won't know the field_id, sometimes we won't know the cat_id.

    // Grab category_id if not available
		if ( empty($this->category_id))
		{
  		if (!empty($this->category_url_title))
  		{
  		  $key   = 'cat_url_title';
  			$value = $this->category_url_title;
  		}
  		else
  		{
  		  $key   = 'cat_name';
  			$value = $this->category_name;
  		}

  		$cache_key     = $this->cache_key('cat_id', $key, $value);
  		$cat_id_cached = ee()->cache->get($cache_key);
  		if (empty($cat_id_cached))
  		{
    		$this->id(); // Don't know what this will return, e.g. in lieu of category pair, but if it works it will cache the category_id
  		}
  		if ( ! ($this->category_id = ee()->cache->get($cache_key)))
  		{
  		  // fail
    		return $this->default_value; // No reason for error message, above will do that.
  		}
		}

    // Grab field_id if not available
    // (Though if field_id IS provided and field_id_{field_id} is not a column on the table... leaving the onus on the developer.)
		if (empty($this->field_id))
		{
	    // Maybe we already know the field_id?
		  $field_id_cache_key = $this->cache_key('field_id', $this->field_short_name);
		  $field_id_cached    = ee()->cache->get($field_id_cache_key);
		  if ( ! empty($field_id_cached))
		  {
  		  $this->field_id = $field_id_cached;
		  }
		  else
		  {
    		// Even though group_id is a column in the exp_category_fields table,
    		// apparently field_names are unique to a site.
    		ee()->db->select('field_id')
    		             ->from('exp_category_fields')
    		             ->where('site_id', $this->site)
    		             ->where('field_name', $this->field_short_name);
  
        $results = ee()->db->get();
        if ($results->num_rows() > 0) 
        {
          $field_id_cached = $results->row('field_id');
          ee()->cache->save($field_id_cache_key, $field_id_cached);
          $this->field_id = $field_id_cached;
        }
        else
        {
  				// fail gracefully
  				//ee()->session->cache[__CLASS__]['field_id'][$this->field_short_name] = '';
  				// How to do this with new Cache Class?
  				
  				if ($this->_debug)
  				{
  					show_error(__CLASS__.' error: category field not found.');
  				}
  				return $this->default_value;
        }
		  }
		}
		
		$field_cache_value = $this->category_id . ':' . $this->field_id;
		$field_value_cache_key = $this->cache_key('field_value', $this->category_id, $this->field_id);
		$field_value_cached    = ee()->cache->get($field_value_cache_key);
	  if (empty($field_value_cached))
	  {
  		$results = ee()->db->select('field_id_'.$this->field_id)
  		                        ->from('exp_category_field_data')
  		                        ->where('site_id', $this->site)
  		                        ->where('cat_id', $this->category_id)
  		                        ->get();
  		
  		if ($results->num_rows() > 0)
  		{
  		  $field_value_result = $results->row('field_id_'.$this->field_id);
    		if ( ! empty($field_value_result))
    		{
          $field_value_cached = $field_value_result;
      		ee()->cache->save($field_value_cache_key, $field_value_result);
    		}
  		}
  		else
  		{
  			// fail gracefully
  			//ee()->session->cache[__CLASS__]['field_value'][$field_cache_value] = '';
  			// How to do this with new Cache Class?
  			
  			if ($this->_debug)
  			{
  				show_error(__CLASS__.' error: category field value was not found.');
  			}
  		}
		}
		// If the value hasn't changed cache and return the default
	  if (empty($field_value_cached))
	  {
	    $field_value_cached = $this->default_value;
		  ee()->cache->save($field_value_cache_key, $this->default_value);  	  
	  }

		// Sorry, neither tag pair check, nor category field value formatting.
		return $field_value_cached;
	}
	
	/** 
	 * cache_key
	 * Shortcut for setting/retrieving Cache Class keys
	 *
	 * @access public
	 * @return string
	 */
	public function cache_key($col, $key, $value = '')
	{
  	return '/'.__CLASS__.'/'.$this->category_group.'/'.$col.'/'.$key . (strlen($value) ? '/'.$value : '');
	}

	// usage instructions
	function usage() 
	{
  		ob_start();
?>
-------------------
HOW TO USE
-------------------

Convert between category name, category id and category url title.
Query results are cached, so you can use the same tag multiple times 
in your template without additional overhead. 

Tags:
{exp:cat2:id}
{exp:cat2:name}
{exp:cat2:url_title}
{exp:cat2:field_value}

Parameters:
category_url_title=
category_name=
category_id=
category_group=
debug="yes|no"

Example use:

category_id from category_url_title:
{exp:cat2:id category_url_title="my_category" category_group="5"}

category_id from category_name:
{exp:cat2:id category_name="my category" category_group="5"}

category_name from category_id:
{exp:cat2:name category_id="25" category_group="5"}

category_name from category_url_title:
{exp:cat2:name category_url_title="my_category" category_group="5"}

category_url_title from category_id:
{exp:cat2:url_title category_id="25" category_group="5"}

category_url_title from category_name:
{exp:cat2:url_title category_name="my category" category_group="5"}

category_field from field_short_name and category
{exp:cat2:field_value field_short_name="my_field" category_url_title="my_category" category_group="5" default_value="Default value"}

Can also be used as a tag pair, e.g.:

{exp:cat2:id category_url_title="my_category" category_group="5" parse="inward"}
	{category_id}
{/exp:cat2:id}

{exp:cat2:name category_id="25" category_group="5" parse="inward"}
	{category_name}
{/exp:cat2:name}

{exp:cat2:url_title category_id="25" category_group="5" parse="inward"}
	{category_url_title}
{/exp:cat2:url_title}


	<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}	
}
