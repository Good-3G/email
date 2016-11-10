<?php
/**
* Copyright (c) 2016, Leon Sorokin
* All rights reserved.
*
* NestedSet.php
* Unobtrusive MPTT / nested set and tree manipulation
* http://en.wikipedia.org/wiki/Nested_set_model
* http://www.wallpaperama.com/forums/mptt-modified-preorder-tree-traversal-php-tree-menu-script-t5713.html
*/

class NestedSet {
	public static $l_key = 'lft';		// left
	public static $r_key = 'rgt';		// right
	public static $c_key = 'kids';		// children
	public static $d_key = 'lvl';		// depth
//	public static $o_key = 'pos';		// order/position (of adjacent children)
	public static $p_key = 'prt';		// parent

	// enumerates nested set's left and right values
	public static function enumTree($tree, &$ctr = 1, $lvl = 0)
	{
		$tree->{self::$d_key} = $lvl;

		$tree->{self::$l_key} = $ctr++;
		foreach ($tree->{self::$c_key} as &$k)
			$k = self::enumTree($k, $ctr, $lvl + 1);
		$tree->{self::$r_key} = $ctr++;

		return $tree;
	}

	// flattens nodes, killing children and parents
	public static function fromTree($tree, $init = TRUE)
	{
		$init && $tree = self::enumTree($tree);

		$kids = $tree->{self::$c_key};
		unset($tree->{self::$c_key});
		unset($tree->{self::$p_key});
		$arr = [$tree];
		foreach ($kids as $k)
			$arr = array_merge($arr, self::fromTree($k, FALSE));
		return $arr;
	}

	public static function strip($node)
	{
		unset($node->{self::$l_key});
		unset($node->{self::$r_key});
		unset($node->{self::$d_key});

		return $node;
	}

	public static function toTree($flat)
	{
		$plft	= 0;			// previous left value
		$stack	= [];

		// append a closing trigger element, since stack rollups triggered by lft diffs
		$flat[] = (object)[self::$l_key => $flat[0]->{self::$r_key}];
		$parents = [];

		foreach ($flat as $itm) {
			$itm = clone $itm;
			$ld = $itm->{self::$l_key} - $plft;

			// rollup
			if ($ld != 1) {
				while (--$ld) {
					$itm2 = array_pop($stack);
					$par = end($stack);
					$par->{self::$c_key}[] = self::strip($itm2);

					// see if current parents list is too deep for $itm2, else its parent needs to be added
					$too_deep = false;

					foreach ($parents as $key => $prt_node) {
						if ($prt_node->id == $par->id) {
							$parents = array_slice($parents, 0, $key+1);
							$too_deep = true;
							break;
						}
					}
					if (!$too_deep) {
						$parents[] = self::strip($par);
					}
					$itm2->{self::$p_key} = $parents;
				}
			}

			$itm->{self::$c_key} = [];
			$itm->{self::$p_key} = $parents;

			$stack[] = $itm;
			$plft = $itm->{self::$l_key};
		}

		return self::strip($stack[0]);
	}

	/**
	 * Inserts a new node into a tree
	 *
	 * @param stdClass	$new_node	Node to be added
	 * @param stdClass 	$tree 		Tree to add the node to
	 * @param int 		$parent_id	Id of the intended parent of the new node
	 * @param int 		$pos 		(Optional) Position of the new node among siblings
	 *								Defaults to start of
	 * @static
	 * @access public
	 */
	public static function insertNode($new_node, $tree, $parent_id, $pos = FALSE){
		if ($tree->id == $parent_id) {
			if (!$pos) {
				$tree->{self::$c_key}[] = $new_node;
			} else {
				array_splice($tree->{self::$c_key}, $pos, 0, [$new_node]);
			}
			$new_node->{self::$p_key} = $tree;
		} else {
			foreach($tree->{self::$c_key} as $node) {
				self::insertNode($new_node, $node, $parent_id, $pos);
			}
		}
	}

	/**
	 * Deletes a node by id
	 * @param int 		$id 	Id of the node to remove
	 * @param stdClass	$tree 	Tree that contains node to remove
	 *
	 * @static
	 * @access public
	 */
	public static function deleteNode($id, $tree) {
		if (isset($tree->{self::$c_key})){
			foreach ($tree->{self::$c_key} as $n => $k) {
				if ($k->id == $id) {
					unset($tree->{self::$c_key}[$n]);
					return;
				} else {
					self::deleteNode($id, $k);
				}
			}
		}
	}
}