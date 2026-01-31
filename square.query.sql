select 
					ifnull( t.square_token, '') as token,
					1_stock_master.stock_id, 
					a.description, a.long_description, a.category, a.lowstock,  
					ifnull( c.c_qty, 0 ) as hg_qty, 	
					ifnull( b.b_qty, 0 ) as hold_qty, 	
					p.price as price, q.price as registered,
					if( a.inactive, 'N', 'Y') as Square_Online_Item_Visibility,
					if( a.inactive, 'N', 'Y') as enabled_hg,
					'0.00' as weight,
					CURDATE() - INTERVAL 56 DAY as sale_start_date,
					CURDATE() - INTERVAL 28 DAY as sale_end_date,
					q.price as sale_price, 'Physical' as Item_Type, 'Y' as sync_with_square
				from 
					1_stock_master 
				left join 
					( select p.stock_id, p.price 
					from 1_prices p 
					where p.sales_type_id=1 and p.curr_abrev='CAD' 
					) as p
				on 1_stock_master.stock_id = p.stock_id
				left join 
					( select t.stock_id, t.square_token
					from 1_square_tokens t 
					) as t
				on 1_stock_master.stock_id = t.stock_id
				left join 
					( select q.stock_id, q.price 
					from 1_prices q 
					where q.sales_type_id=3 and 	q.curr_abrev='CAD' 
					) as q
				on 1_stock_master.stock_id = q.stock_id
				left join 
					( select c.stock_id, c.loc_code as c_loc_code,  sum( c.qty ) as c_qty 
					from 1_stock_moves c 
					where c.loc_code='" . $this->PRIMARY_LOC . "' 
					group by c.stock_id 
					) as c
				on 1_stock_master.stock_id=c.stock_id
				left join 
					( select b.stock_id, b.loc_code as b_loc_code,  sum( b.qty ) as b_qty 
					from 1_stock_moves b 
					where b.loc_code='" . $this->SECONDARY_LOC . "' 
					group by b.stock_id 
					) as b
				on 1_stock_master.stock_id=b.stock_id
				LEFT JOIN 
					( select s.stock_id, s.description,	 s.long_description, s.inactive, c.description as category, 	r.reorder_level as lowstock
            				from    1_stock_master s, 1_stock_category c, 1_loc_stock r
            				where   s.category_id = c.category_id and r.loc_code='" . $this->PRIMARY_LOC . "' and r.stock_id=s.stock_id 
					) as a
				ON a.stock_id = 1_stock_master.stock_id
				";
/*
				where 
					1_stock_master.loc_code='" . $this->PRIMARY_LOC . "' 
				group by 
					1_stock_master.stock_id ";
*/
