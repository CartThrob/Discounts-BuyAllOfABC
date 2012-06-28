<?php if ( ! defined('CARTTHROB_PATH')) Cartthrob_core::core_error('No direct script access allowed');

class Cartthrob_discount_buy_all_of_abc extends Cartthrob_discount
{
	public $title = 'Buy All of a-b-c, get discount or get d free';
	public $settings = array(
		array(
			'name' => 'discount_quantity',
			'short_name' => 'get_x_free',
			'note' => 'enter_the_number_of_items',
			'type' => 'text'
		),
		array(
			'name' => 'percentage_off',
			'short_name' => 'percentage_off',
			'note' => 'enter_the_percentage_discount',
			'type' => 'text'
		),
		array(
			'name' => 'amount_off',
			'short_name' => 'amount_off',
			'note' => 'enter_the_discount_amount',
			'type' => 'text'
		),
		array(
			'name' => 'Free Item?',
			'short_name' => 'free_item',
			'note' => 'Selecting yes will apply a discount equal to the lowest priced qualifying item',
			'type' => 'select',
			'default'	=> 'No',
			'options' => array('FALSE' => 'No', 'TRUE'=> 'Yes'),
		),
		array(
			'name' => 'Free entry_id',
			'short_name' => 'free_entry_id',
			'note' => 'If using Free Item, set the entry_id, aka. d',
			'type' => 'text'
		),
		array(
			'name' => 'qualifying_entry_ids',
			'short_name' => 'entry_ids',
			'note' => 'Separate multiple entry_ids by comma (ie. a,b,c)',
			'type' => 'text'
		),
		array(
			'name' => 'per_item_limit',
			'short_name' => 'item_limit',
			'note' => 'per_item_limit_note',
			'type' => 'text'
		),
	);
	
	function get_discount()
	{
		
		$discount 			= 0;
		$entry_ids 			= array();
		$free_item = FALSE;
		
		// CHECK AMOUNTS AND PERCENTAGES
		if ($this->plugin_settings('percentage_off') !== '')
		{
			$percentage_off = ".01" * $this->core->sanitize_number( $this->plugin_settings('percentage_off') );

			if ($percentage_off > 100)
			{
				$percentage_off = 100; 
			}
			else if ($percentage_off < 0)
			{
				$percentage_off = 0; 
			}
		}
		elseif ($this->plugin_settings('free_item') == 'TRUE')
		{
			$free_item = TRUE;
		}
		else
		{
			$amount_off = $this->core->sanitize_number( $this->plugin_settings('amount_off') );
		}
		
		// CHECK ENTRY IDS
		if ( $this->plugin_settings('entry_ids') )
		{
			$entry_ids = preg_split('/\s*[|,-]\s*/', trim( $this->plugin_settings('entry_ids') ));
		}
		
		if ( ! $entry_ids)
		{
			return 0;
		}
		
		//doesn't have all of them
		if (array_diff($entry_ids, $this->core->cart->product_ids()))
		{
			return 0;
		}
		
		if ($free_item)
		{
			if ($this->plugin_settings('free_entry_id') && ! in_array($this->plugin_settings('free_entry_id'), $this->core->cart->product_ids()))
			{
				return 0;
			}
		}
		
		$item_limit = ( $this->plugin_settings('item_limit') ) ? $this->plugin_settings('item_limit') : FALSE;
			
		$items = array();

		foreach ($this->core->cart->items() as $item)
		{
			//if it matches item "d" of get D free, return that item's price
			if ($free_item && $item->product_id() && $this->plugin_settings('free_entry_id') && $item->product_id() == $this->plugin_settings('free_entry_id'))
			{
				return $item->price();
			}
			
			if ( $item->product_id() && in_array( $item->product_id(), $entry_ids))
			{
				for ($i=0; $i<$item->quantity() ;$i++)
				{
					$items[] = $item->price(); 
				}
			}
		}
			
		// sort the items so the lowest prices are last
		rsort($items);
		
 		$counts = array();
		reset($items);			

		while (($price = current($items)) !== FALSE)
		{
			$key = key($items);

			$count = count($items);
			while($count > 0 )
			{
				if ($item_limit !== FALSE && $item_limit < 1)
				{
					next($items);
						continue 2;
				}

				if ($this->plugin_settings('get_x_free'))
				{
					$free_count = ($count > $this->plugin_settings('get_x_free')) ? $this->plugin_settings('get_x_free') : $count;
				}
				else
				{
					$free_count = $count; 
				}
				
				if (isset($percentage_off))
				{
					//get the lowest price by grabbing the last array item
					//since our array is sorted by price
					for ($i=0;$i<$free_count;$i++)
					{
						$discount += end($items) * $percentage_off;
						array_pop($items);
					}

					//go back to where we were
					reset($items);
					while ($key != key($items)) next($items);
				}
				elseif (isset($free_item) && $free_item==TRUE)
				{
					for ($i=0;$i<$free_count;$i++)
					{
						$discount += end($items);
						array_pop($items);
					}
				}
				else
				{
					for ($i=0;$i<$free_count;$i++)
					{
						array_pop($items);
						$discount += $amount_off;
					}
				}

				$count -= $free_count;

				if ($item_limit !== FALSE)
				{
					$item_limit--;
				}
			}

			next($items);
		}

		return $discount;
	}

	function validate()
	{
		if ( ! $this->plugin_settings('entry_ids'))
		{
			$this->set_error('Must have all products to qualify');
			return FALSE;
		}
		
		$entry_ids = preg_split('/\s*[|,-]\s*/', trim($this->plugin_settings('entry_ids')));
		
		if (array_diff($entry_ids, $this->core->cart->product_ids()))
		{
			$this->set_error('Must have all products to qualify');
			return FALSE;
		}
		
		return TRUE;
	}
	
}