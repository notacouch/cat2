<?php

$plugin_info = array(
  'pi_name' => 'Cat2',
  'pi_version' =>'1.1.1',
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
		$this->EE =& get_instance();	
		$this->site = $this->EE->config->item('site_id');
		
		// register parameters
		$this->category_url_title = strtolower($this->EE->TMPL->fetch_param('category_url_title', ''));
		$this->category_name = strtolower($this->EE->TMPL->fetch_param('category_name', ''));
		$this->category_id = preg_replace("/[^0-9]/", '', $this->EE->TMPL->fetch_param('category_id', NULL));
		$this->category_group = $this->EE->TMPL->fetch_param('category_group', '');
		$this->field_short_name = strtolower($this->EE->TMPL->fetch_param('field_short_name', ''));
		$this->field_id = preg_replace("/[^0-9]/", '', $this->EE->TMPL->fetch_param('field_id', NULL));
		$this->default_value = $this->EE->TMPL->fetch_param('default_value', '');
		$this->_debug = (bool) preg_match('/1|on|yes|y/i', $this->EE->TMPL->fetch_param('debug'));	
		
		// set up cache
		if ( ! isset($this->EE->session->cache[__CLASS__]))
        {
            $this->EE->session->cache[__CLASS__] = array();
        }
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
		if ( ! isset($this->EE->session->cache[__CLASS__][$col][$value]) )
		{
			// query
			$this->EE->db->select($col);
			$this->EE->db->from('exp_categories');
			$this->EE->db->where('site_id', $this->site);
		
			if ($key == 'cat_id')
			{
				$this->EE->db->where($key, $value);
			}
			else
			{
				$this->EE->db->where("LOWER({$key})", $value);
			}
			
			if ( ! empty($this->category_group))
			{
				$this->EE->db->where('group_id', $this->category_group);
			}
			
			// run the query
			$results = $this->EE->db->get();
			
			if ($results->num_rows() > 0) 
			{
				$this->EE->session->cache[__CLASS__][$col][$value] = $results->row($col);
			}
			else
			{
				// fail gracefully
				$this->EE->session->cache[__CLASS__][$col][$value] = '';
				
				if ($this->_debug)
				{
					show_error(__CLASS__.' error: category not found.');
				}
			}
		}
		
		// is this a tag pair?
		$tagdata = $this->EE->TMPL->tagdata;
	
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
			return $this->EE->TMPL->swap_var_single(
							$tmpl_variable, 
							$this->EE->session->cache[__CLASS__][$col][$value], 
							$tagdata
					);
		}
		else
		{
			// output direct
			return $this->EE->session->cache[__CLASS__][$col][$value];
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
  			$value = $this->category_url_title;
  		}
  		else
  		{
  			$value = $this->category_name;
  		}
  		if (empty($this->EE->session->cache[__CLASS__]['cat_id'][$value]))
  		{
    		$this->id(); // Don't know what this will return, e.g. in lieu of category pair
  		}
  		if ( ! ($this->category_id = $this->EE->session->cache[__CLASS__]['cat_id'][$value]))
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
		  if ( ! empty($this->EE->session->cache[__CLASS__]['field_id'][$this->field_short_name]))
		  {
  		  $this->field_id = $this->EE->session->cache[__CLASS__]['field_id'][$this->field_short_name];
		  }
		  else
		  {
    		// Even though group_id is a column in the exp_category_fields table,
    		// apparently field_names are unique to a site.
    		$this->EE->db->select('field_id')
    		             ->from('exp_category_fields')
    		             ->where('site_id', $this->site)
    		             ->where('field_name', $this->field_short_name);
  
        $results = $this->EE->db->get();
        if ($results->num_rows() > 0) 
        {
          $this->EE->session->cache[__CLASS__]['field_id'][$this->field_short_name] = $results->row('field_id');
          $this->field_id = $this->EE->session->cache[__CLASS__]['field_id'][$this->field_short_name];
        }
        else
        {
  				// fail gracefully
  				$this->EE->session->cache[__CLASS__]['field_id'][$this->field_short_name] = '';
  				
  				if ($this->_debug)
  				{
  					show_error(__CLASS__.' error: category field not found.');
  				}
  				return $this->default_value;
        }
		  }
		}
		
		$field_cache_value = $this->category_id . ':' . $this->field_id;
	  if (empty($this->EE->session->cache[__CLASS__]['field_value'][$field_cache_value]))
	  {
  		$results = $this->EE->db->select('field_id_'.$this->field_id)
  		                        ->from('exp_category_field_data')
  		                        ->where('site_id', $this->site)
  		                        ->where('cat_id', $this->category_id)
  		                        ->get();
  		
  		if ($results->num_rows() > 0)
  		{
    		$this->EE->session->cache[__CLASS__]['field_value'][$field_cache_value] = $results->row('field_id_'.$this->field_id);    		
    		if (empty($this->EE->session->cache[__CLASS__]['field_value'][$field_cache_value]))
    		{
    		  $this->EE->session->cache[__CLASS__]['field_value'][$field_cache_value] = $this->default_value;
    		}
  		}
  		else
  		{
  			// fail gracefully
  			$this->EE->session->cache[__CLASS__]['field_value'][$field_cache_value] = '';
  			
  			if ($this->_debug)
  			{
  				show_error(__CLASS__.' error: category field value was not found.');
  			}
  			return $this->default_value;
  		}
		}
		// Sorry, neither tag pair check, nor category field value formatting.
		return $this->EE->session->cache[__CLASS__]['field_value'][$field_cache_value];  		
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