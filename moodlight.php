<?php
	/*  Copyright 2008  Gasquez Florian  (email : f.gasquez@weelya.com)

	    This program is free software; you can redistribute it and/or modify
	    it under the terms of the GNU General Public License as published by
	    the Free Software Foundation; either version 2 of the License.

	    This program is distributed in the hope that it will be useful,
	    but WITHOUT ANY WARRANTY; without even the implied warranty of
	    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	    GNU General Public License for more details.

	    You should have received a copy of the GNU General Public License
	    along with this program; if not, write to the Free Software
	    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
	*/

	/**
	 * @package moodlight
	 * @author Gasquez Florian <f.gasquez@weelya.com>
	 * @version 1.0.3
	 */
	/*
		Plugin Name: moodlight
		Plugin URI: http://www.boolean.me/2008/12/12/moodlight-plugin-wordpress/
		Description: Moodlight allows your visitors to add their mood on posts via comments. 
		Author: Gasquez Florian
		Version: 1.0.3
		Author URI: http://www.boolean.me
	*/
	
	$moodlight_version        = "1.0.3";
	$moodlight_comments 	  = NULL;
	$moodlight_current_active = NULL;
	$moodlight_current_post   = NULL;
	$moodlight_pings	  = TRUE;

	/* Install the moodlight plugin */
	function moodlight_install()
	{
		global $wpdb, $moodlight_version;
		
		$table_name = $wpdb->prefix . 'moodlight';
		
		if ($wpdb->get_var('show tables like '.$table_name) != $table_name) {
			$sql_query = '
				CREATE TABLE '.$table_name.' (
					`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					`id_post` INT NOT NULL ,
					`id_comment` INT NOT NULL ,
					`moodlight` INT NOT NULL ,
					INDEX ( `id_post` , `id_comment` )
				);
			';
			
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			
			dbDelta($sql_query);
		}
		
		$table_name = $wpdb->prefix . 'moodlight_post';
		
		if ($wpdb->get_var('show tables like '.$table_name) != $table_name) {
			$sql_query = '
				CREATE TABLE '.$table_name.' (
					`id_post` INT NOT NULL ,
					PRIMARY KEY ( `id_post` )
				);
			';
			
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			
			dbDelta($sql_query);
		}
		
		if (!get_option('moodlight_version')) {
			add_option("moodlight_version", $moodlight_version);
			moodlight_ping('[install='.$moodlight_version.']');
		} else {
			if (get_iption('moodlight_version') != $moodlight_version) {
				moodlight_ping('[update='.$moodlight_version.']');
				update_option("moodlight_version", $moodlight_version);
			}
		}
		
		$values          = array('comment_pos', 'comment_neu', 'comment_neg', 'post_eval');
		$values_txt      = array('Positif', 'Neutre', 'Négatif', 'Humeur du post');
		
		foreach ($values as $k => $v) {
			if(get_option("moodlight_".$v) === FALSE) {
				add_option("moodlight_".$v, $values_txt[$k]);
			}
		}
	}
	
	/* Updates */
	
	/* Evaluate a comment */
	function moodlight_note($params)
	{
		global $wpdb;
		

			
		$table_name = $wpdb->prefix . 'moodlight';
		$exist	    = array(0, 5, 10);
		
		$note       = intval($params['note']);
		$id_post    = intval($params['id_post']);
		$id_comment = intval($params['id_comment']);

		if (!moodlight_is_post_active($id_post)) 
			return;
			
		if (in_array($note, $exist)) {
			$insert = '
				INSERT INTO '.$table_name.'(id_post, id_comment, moodlight)
				VALUES ('.$id_post.', '.$id_comment.', "'.$note.'")
			';
			
			$wpdb->query($insert);
			
			moodlight_ping('[note='.$id_post.']');
		}
	}
	
	/* Init moodlight function */
	function moodlight_init()
	{
		moodlight_install();
	}
	
	/* Ty to Anthony Catel <a.catel@weelya.com> */
	function avg2color($avg, $n)
	{
		$v = ceil(($avg / $n) * 186)+30; // (42)
		$r = ceil((($n - $avg) / $n) * 186)+30; // (42 again)
		$b = 15;

		$r = ($r > 123 ? 123 : $r);
		$v = ($v > 123 ? 123 : $v);		
		return array($r, $v, $b);
	}
	
	/* RGB 2 HEX */
	function rgb2hex($rgb_color) {
		$rgb    = explode(',', $rgb_color);
		$rgb[0] = str_replace('rgb(', '', $rgb[0]);
		$rgb[2] = str_replace(')', '', $rgb[2]);
		
		array_splice($rgb, 3);

		for ($x = 0; $x < count($rgb); $x++) {
		    $rgb[$x] = strtoupper(str_pad(dechex($rgb[$x]), 2, 0, STR_PAD_LEFT));
		}

		return implode("", $rgb);
	} 
	
	/* Front & backend CSS */
	function moodlight_css()
	{
		echo '
			<link rel="stylesheet" href="'.WP_PLUGIN_URL.'/moodlight/css/moodlight.css" type="text/css" media="screen" />
		';
	}

	/* Display colors */
	function colors2div($rvb, $n)
	{
		return '<div class="moodlight_color" style="background-color: rgb('.$rvb[0].', '.$rvb[1].', '.$rvb[2].')">'.$n.'</div>';
	}

	/* Addmenu & backend stuff */
	function moodlight_menu() {
		add_options_page('Options Moodlight', 'Moodlight', 8, __FILE__, 'moodlight_options');
		
		if ( function_exists('dbx_post_sidebar') ) {
			add_action('dbx_post_sidebar', 'moodlight_publish');
		}
	}

	/* List post use moodlight */
	function moodlight_posts()
	{
		global $wpdb;
	
		$query = '
			SELECT '.$wpdb->prefix.'posts.ID, post_title FROM '.$wpdb->prefix.'posts, '.$wpdb->prefix.'moodlight, '.$wpdb->prefix.'moodlight_post WHERE '.$wpdb->prefix.'moodlight.id_post='.$wpdb->prefix.'posts.ID AND '.$wpdb->prefix.'moodlight_post.id_post='.$wpdb->prefix.'posts.ID GROUP BY '.$wpdb->prefix.'posts.ID ORDER BY '.$wpdb->prefix.'posts.ID DESC
		';

		$posts = $wpdb->get_results($query, OBJECT);
		
		return $posts;
	}
	
	function moodlight_all_posts()
	{
		global $wpdb;

		$query = '
			SELECT '.$wpdb->prefix.'posts.ID, post_title FROM '.$wpdb->prefix.'posts WHERE post_type="post" ORDER BY '.$wpdb->prefix.'posts.ID DESC
		';
		
		return $wpdb->get_results($query, OBJECT);
	}
	
	/* Display post active */
	function moodlight_post_active($id_post)
	{
		global $wpdb;
		
		$query = '
			SELECT id_post
			FROM '.$wpdb->prefix.'moodlight_post
			WHERE id_post='.intval($id_post).'
		';
		
		if (count($wpdb->get_results($query, OBJECT)) >= 1) {
			$return = '
				Le plugin est actuellement activé pour ce post : <a class="button" href="options-general.php?page=moodlight/moodlight.php&a=active&opt=disable&id_post='.$id_post.'">Désactiver Moodlight</a>
			';
		} else {
			$return = '
				Le plugin est actuellement désactivé pour ce post :  <a class="button" href="options-general.php?page=moodlight/moodlight.php&a=active&opt=enable&id_post='.$id_post.'">Activer Moodlight</a>
			';
		}
		
		return $return;
	}
	
	/* Return all actives posts */
	function moodlight_all_active() 
	{
		global $wpdb;
		
		$query = '
			SELECT id_post FROM '.$wpdb->prefix.'moodlight_post
		';
		
		return $wpdb->get_results($query, OBJECT);
	}
	
	
	/* Display post stats */
	function moodlight_stats_id($id_post)
	{
		global $wpdb;
	
		$query = '
			SELECT moodlight, id_comment
			FROM '.$wpdb->prefix.'moodlight
			WHERE id_post = '.intval($id_post).'
			ORDER BY id
		';
	
		$comments = $wpdb->get_results($query, OBJECT);
		
		$array_comments = array();
		foreach ($comments as $v) {
			$array_comments[] = $v->moodlight;
		}

		$n = get_option('moodlight_n_color');
		
		if (!$n) {
			$n = 2;
		}
		
		$html = '';
		for ($i = 1; $i <= count($array_comments); $i++) {
			$tmp_array = array_slice($array_comments, 0, $i);
			$tmp_avg = ceil((array_sum($tmp_array)/count($tmp_array)));
			$color = avg2color(ceil(($tmp_avg*$n)/10), $n);
			$html .= '
				<div class="moodlight_stats_bar" style="height:'.round($tmp_avg*10).'px; margin-top: '.(100-round($tmp_avg*10)).'px; background-color: rgb('.$color[0].', '.$color[1].', '.$color[2].')" />'.$tmp_avg.'</div>
			';
		}
		
		return '<div style="overflow: auto; height:120px; "><div style="width: '.(18*count($array_comments)).'px">'.$html.'</div></div>';
	}


	/* post avg */
	function moodlight_mood()
	{
		global $wpdb, $post;
		
		$id_post = $post->ID;

		$query = '
			SELECT AVG( moodlight )
			FROM `'.$wpdb->prefix.'moodlight`
			WHERE id_post='.intval($id_post).'
			LIMIT 1
		';

		$mood = $wpdb->get_var($query);
		
		if ($mood == null) {
			$mood = 5;
		}

		$n = get_option('moodlight_n_color');
		
		if (!$n) {
			$n = 2;
		}
		
		return array(avg2color(ceil(($mood*$n)/10), $n), ceil(($mood*$n)/10));
	}
	
	/* tpl for post */
	function moodtpl_post() {
		if (!moodlight_is_post_active())
			return;
			
		$result = moodlight_mood();
		$color = $result[0];

		$n = get_option('moodlight_n_color');
		
		if (!$n) {
			$n = 2;
		}
		
		echo '
			<span class="moodlight_post" style="background-color: rgb('.$color[0].', '.$color[1].', '.$color[2].')">'.get_option('moodlight_post_eval').' '.$result[1].'/'.$n.'</span>
		';
	}
	
	/* tpl for post note */
	function moodtpl_post_note($return = false)
	{
		if (!moodlight_is_post_active()) 
			return;
	
		$result = moodlight_mood();
		$color = $result[0];

		$n = get_option('moodlight_n_color');
		
		if (!$n) {
			$n = 2;
		}
		
		if ($return) {
			return $result[1].'/'.$n;
		} else {
			echo $result[1].'/'.$n;
		}
	}
	
	function moodtpl_post_chart() 
	{
		global $wpdb, $post;
		
		if (!moodlight_is_post_active()) 
			return;
		
		$id_post = $post->ID;
		
		$query = '
			SELECT moodlight
			FROM '.$wpdb->prefix.'moodlight
			WHERE id_post = '.intval($id_post).'
			ORDER BY id
		';
		
		$n = get_option('moodlight_n_color');
		
		$comments = $wpdb->get_results($query, OBJECT);
		
		$neg = $neu = $pos = 0;
		foreach ($comments as $v) {
			if ($v->moodlight == 10) {
				$pos++;
			} else if ($v->moodlight == 5) {
				$neu++;
			} else {
				$neg++;
			}
		}
		
		$coms = count($comments);
		$s    = '';
		if ($coms > 1) {
			$s = 's';
		}

		echo '<img src="http://chart.apis.google.com/chart?chtt='.__('Ambiance des', 'moodlight').' '.$coms.' '.__('commentaire', 'moodlight').$s.' : '.moodtpl_post_note(true).'&amp;chts='.rgb2hex(moodtpl_post_color(true)).',12&amp;chs=300x150&amp;chf=bg,s,ffffff&amp;cht=p3&amp;chd=t:'.$pos.','.$neu.','.$neg.'&amp;chl='.__('Positif', 'moodlight').'|'.__('Neutre', 'moodlight').'|'.__('Négatif', 'moodlight').'&amp;chco=1E7B0F,7B7B0F,7B1E0F" alt="Moodlight"/>';
	}
	
	/* tpl for post color */
	function moodtpl_post_color($return = false)
	{
		if (!moodlight_is_post_active()) 
			return;
			
		$result = moodlight_mood();
		$color = $result[0];

		$n = get_option('moodlight_n_color');
		
		if (!$n) {
			$n = 2;
		}
		
		if ($return) {
			return 'rgb('.$color[0].', '.$color[1].', '.$color[2].')';
		} else {
			echo 'rgb('.$color[0].', '.$color[1].', '.$color[2].')';
		}
	}
	
	/* tpl for comment */
	function moodtpl_comment() {
		global $wpdb, $post, $comment, $moodlight_comments;
		
		if (!moodlight_is_post_active()) 
			return;
			
		if ($moodlight_comments == NULL) {
			$query = '
				SELECT id_comment, moodlight FROM '.$wpdb->prefix.'moodlight WHERE id_post = '.intval($post->ID).'
			';
		
			$tmp_comments = $wpdb->get_results($query, OBJECT);
		
			foreach ($tmp_comments as $v) {
				$moodlight_comments[$v->id_comment] = intval($v->moodlight);
			}
		}
		
		$colors = array(0 => 'rgb(123, 30, 15)', 5 => 'rgb(123, 123, 15)', 10 => 'rgb(30, 123, 15)');
		$lang   = array(0 => get_option('moodlight_comment_neg'), 5 => get_option('moodlight_comment_neu'), 10 => get_option('moodlight_comment_pos'));
		
		if ($moodlight_comments[$comment->comment_ID] !== NULL) {
			echo '
				<div class="moodlight_comment" style="background-color: '.$colors[$moodlight_comments[$comment->comment_ID]].';"> '.$lang[$moodlight_comments[$comment->comment_ID]].' </div>
			';
		}
	}
	
	/* tpl for comment color */
	function moodtpl_comment_color() {
		global $wpdb, $post, $comment, $moodlight_comments;
		
		if (!moodlight_is_post_active()) 
			return;
		
		if ($moodlight_comments == NULL) {
			$query = '
				SELECT id_comment, moodlight FROM '.$wpdb->prefix.'moodlight WHERE id_post = '.intval($post->ID).'
			';
		
			$tmp_comments = $wpdb->get_results($query, OBJECT);
		
			foreach ($tmp_comments as $v) {
				$moodlight_comments[$v->id_comment] = intval($v->moodlight);
			}
		}
		
		$colors = array(0 => 'rgb(123, 30, 15)', 5 => 'rgb(123, 123, 15)', 10 => 'rgb(30, 123, 15)');
		
		if ($moodlight_comments[$comment->comment_ID] !== NULL) {
			echo $colors[$moodlight_comments[$comment->comment_ID]];
		} else {
			echo 'transparent';
		}
	}
	
	/* tpl for add comment */
	function moodtpl_add_comment()
	{
		global $post;
		
		if (!moodlight_is_post_active()) 
			return;
			
		$html = '
			<label for="n_moodlight_vote">'.__('Avis sur le sujet', 'moodlight').' :</label>
			<div class="moodlight_container">
				<div class="moodlight_color" style="background-color: rgb(123, 30, 15);"></div> 
				<div class="moodlight_label">
					<input type="radio" id="moodlight_evil" value="0" name="n_moonlight_vote" />
					<label for="moodlight_evil">'.__('Négatif', 'moodlight').'</label>
				</div>
				<div class="moodlight_color" style="background-color: rgb(123, 123, 15);"></div> 
				<div class="moodlight_label">
					<input type="radio" id="moodlight_neutral" value="5" name="n_moonlight_vote" checked="checked"/>
					<label for="moodlight_neutral">'.__('Neutre', 'moodlight').'</label>
				</div>
				<div class="moodlight_color" style="background-color: rgb(30, 123, 15);"></div> 
				<div class="moodlight_label">
					<input type="radio" id="moodlight_good" value="10" name="n_moonlight_vote" />
					<label for="moodlight_good">'.__('Positif', 'moodlight').'</label>
				</div>
			</div>
		';
		
		moodlight_ping('[present='.$post->ID.']');
		
		echo $html;
	}
	
	/* tpl for post percent, ty to http://www.cafe-froid.net/ */
	function moodtpl_post_percent()
	{
		if (!moodlight_is_post_active()) 
			return;
		
		$result = moodlight_mood();
		$color = $result[0];

		$n = get_option('moodlight_n_color');

		if (!$n)
			$n = 2;

		$percent = $result[1] / $n * 100;

		echo round($percent);
	}
	
	/* post filter */
	function moodlight_post($comment = 0)
	{
		$params['id_post']    = $_POST['comment_post_ID'];
		$params['id_comment'] = $comment;
		$params['note']       = $_POST['n_moonlight_vote'];
	
		moodlight_note($params);
	}
	
	/* action & filter */
	function moodlight()
	{
		add_action('admin_menu', 'moodlight_menu');
		add_action('admin_head', 'moodlight_css');
		add_action('wp_head', 'moodlight_css');
		add_filter('comment_post', 'moodlight_post');
	}
	
	/* ping for stats */
	function moodlight_ping($action)
	{
		global $post, $moodlight_pings;
		
		if (!$moodlight_pings)
			return;
		
		if ($post->guid) {
			$url = $post->guid;
		} else {
			$url = get_option('siteurl');
		}
	
		$null = @file_get_contents('http://www.boolean.me/moodlight_ping/?action='.urlencode($action).'&uri='.urlencode($url));
		return;
	}
	
	/* post is enable */
	function moodlight_is_post_active($id_post = NULL)
	{
		global $post, $wpdb, $moodlight_current_active, $moodlight_current_post;
		
		if ($id_post == NULL)
			$id_post = $post->ID;
			
		if ($id_post == $moodlight_current_post && $moodlight_current_active) {
			$return = (bool)$moodlight_current_active;
		} else {
			$return = (bool)$wpdb->get_var('SELECT id_post FROM '.$wpdb->prefix.'moodlight_post WHERE id_post='.$id_post);
			$moodlight_current_post = $id_post;
			$moodlight_current_active = $return;
		}
		
		return $return;
	}

	/* enable or disable post */
	function moodlight_update_enable($type, $id_post) 
	{
		global $wpdb;
		
		// Ugly updates
		$table_name = $wpdb->prefix . 'moodlight_post';
		if ($wpdb->get_var('show tables like '.$table_name) != $table_name) {
			$sql_query = '
				CREATE TABLE '.$table_name.' (
					`id_post` INT NOT NULL ,
					PRIMARY KEY ( `id_post` )
				);
			';
			
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			
			dbDelta($sql_query);
		}
		
		if (!$type) {
			$query = '
				DELETE FROM '.$wpdb->prefix.'moodlight_post WHERE id_post = '.intval($id_post).'
			';
			
			moodlight_ping('[disable='.$id_post.']');
		} else {
			$query = '
				REPLACE INTO '.$wpdb->prefix.'moodlight_post(id_post) VALUES('.intval($id_post).')
			';
			
			moodlight_ping('[enable='.$id_post.']');
		}

		 $wpdb->query($query);
	}

	/* Admin options */
	function moodlight_options() {
		global $wpdb;
	
		$moodlight_divs  = '';
		$values          = array('comment_pos', 'comment_neu', 'comment_neg', 'post_eval');
		$values_txt      = array('Positif', 'Neutre', 'Négatif', 'Humeur du post');
		
		if (count($_POST) >= 1) {
			if ($_POST['n_color']>= 2 && $_POST['n_color'] <= 30 && ($_POST['n_color']+1)%2 != 0) {
				$tmp_n = get_option('moodlight_n_color');
				if (!$tmp_n) {
					add_option("moodlight_n_color", intval($_POST['n_color']));
				} else {
					update_option("moodlight_n_color",  intval($_POST['n_color']));
				}
			}
			
			foreach ($values as $v) {
				$opt = get_option('moodlight_'.$v);
				
				if($opt === FALSE) {
					add_option('moodlight_'.$v, stripslashes(htmlentities(htmlspecialchars($_POST[$v]))));
				} else {
					
					update_option('moodlight_'.$v, stripslashes(htmlspecialchars($_POST[$v])));
				}
			}
		}
				
		$n = get_option('moodlight_n_color');
		
		if (!$n) {
			$n = 2;
		}
		
		for ($i=0; $i<=$n; $i++) {
			$moodlight_divs .= colors2div(avg2color($i, $n), $i);
		}
		
		$n_color_options = '';
		for ($i=2; $i <= 30; $i=($i+3)) {
			if ($n == $i)
				$selected = 'selected="selected"';

			if (($i+1)%2 != 0)
				$n_color_options .= '<option value="'.$i.'" '.$selected.'>'.($i+1).' '.__('couleurs', 'moodlight').'</option>';
			
			if (isset($selected)) 
				unset($selected);
		}
		
		// backend stats;
		$posts      = moodlight_posts();
		$posts_list = '';
		foreach ($posts as $v) {
			if ($v->ID == $_POST['id_post']) 
				$selected = 'selected="selected"';
				
			$posts_list .= '<option '.$selected.' value="'.$v->ID.'">'.$v->post_title.'</option>';
			
			if ($selected)
				unset($selected);
		}
		
		$page_stats = '
			<form method="post" action="">
				<table class="form-table">
					<tr>

						<td>
							<select style="margin-top: 2px; width: 300px;" name="id_post" id="id_post">
								'.$posts_list.'
							</select>

							<input type="submit" name="Submit" class="button-primary" value="'.__('Go !', 'moodlight').'" />
						</td>
					</tr>
				</table>

			</form>
		';
		
		// backend active
		$all_posts  = moodlight_all_posts();
		$actives    = moodlight_all_active();
		$posts_list = '';
		
		$actives_id = array();
		foreach ($actives as $v) {
			$actives_id[] = $v->id_post;
		}
		
		foreach ($all_posts as $v) {
			if ($v->ID == $_REQUEST['id_post']) 
				$selected = 'selected="selected"';
				
			if (!@in_array($v->ID, $actives_id))
				$more = '[Désactivé]';
			else
				$more = '[Activé]';
				
			$posts_list .= '<option '.$selected.' value="'.$v->ID.'">'.$more.' '.$v->post_title.'</option>';
			
			if ($selected)
				unset($selected);
		}
		
		$page_active = '
			<form method="post" action="">
				<table class="form-table">
					<tr>

						<td>
							<select style="margin-top: 2px; width: 300px;" name="id_post" id="id_post">
								'.$posts_list.'
							</select>

							<input type="submit" name="Submit" class="button-primary" value="'.__('Go !', 'moodlight').'" />
						</td>
					</tr>
				</table>

			</form>
		';
		
		if ($_POST['id_post'] && $_GET['a'] == 'stats') {
			$post_title = $wpdb->get_var('SELECT post_title FROM '.$wpdb->prefix.'posts WHERE '.$wpdb->prefix.'posts.ID='.intval($_POST['id_post']));
			$page_stats .= '
				<h3>'.__('Stats de ', 'moodlight').$post_title.'</h3>
				<p>'.__('Evolution de l\'humeur en fonction des commentaires', 'moodlight').'</p>
				'.moodlight_stats_id(intval($_POST['id_post'])).'
			';
		}

		
		if ($_REQUEST['id_post'] && $_GET['a'] == 'active') {
			if ($_GET['opt'] == 'enable') {
				moodlight_update_enable(true, $_REQUEST['id_post']);
			} else if ($_GET['opt'] == 'disable') {
				moodlight_update_enable(false, $_REQUEST['id_post']);
			}
			
			$post_title = $wpdb->get_var('SELECT post_title FROM '.$wpdb->prefix.'posts WHERE '.$wpdb->prefix.'posts.ID='.intval($_REQUEST['id_post']));
			$page_active .= '
				<h3>'.__('Activation sur ', 'moodlight').$post_title.'</h3>
				'.moodlight_post_active(intval($_REQUEST['id_post'])).'
			';
		}
		
		// backend page configuration
		$page_configuration = '
			
				<h3>Configuration de la note</h3>
				<p>
					'.__('Vos articles auront une notre sur', 'moodlight').' '.$n.'.<br /><br />
					'.__('Voici votre échelle de couleur pour les commentaires', 'moodlight').' :
				</p>
				
				<div class="moodlight_container">
					'.$moodlight_divs.'
				</div>
				
				<form method="post" action="">
					<table class="form-table">
						<tr>
							<th scope="row"><label for="n_color">'.__('Nombre de couleurs', 'moodlight').'</label></th>
							<td>
								<select name="n_color" id="n_color">
									'.$n_color_options.'
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="comment_pos">'.__('Texte évaluation positive', 'moodlight').'</label></th>
							<td>
								<input type="text" name="comment_pos" id="comment_pos" value="'.get_option('moodlight_comment_pos').'" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="comment_neu">'.__('Texte évaluation neutre', 'moodlight').'</label></th>
							<td>
								<input type="text" name="comment_neu" id="comment_neu" value="'.get_option('moodlight_comment_neu').'" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="comment_neg">'.__('Texte évaluation négative', 'moodlight').'</label></th>
							<td>
								<input type="text" name="comment_neg" id="comment_neg" value="'.get_option('moodlight_comment_neg').'" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="post_eval">'.__('Texte d\'évaluation d\'un article', 'moodlight').'</label></th>
							<td>
								<input type="text" name="post_eval" id="post_eval" value="'.get_option('moodlight_post_eval').'" />
							</td>
						</tr>
					</table>
					<p class="submit">
						<input type="submit" name="Submit" class="button-primary" value="'.__('Enregister', 'moodlight').'" />
					</p>
				</form>
		';
		
		// backend page documentation
		$page_doc           = '
				<h3>'.__('Fonctionnalités de template', 'moodlight').' :</h3>
				<pre style="font-weight: bold">'.htmlentities('<?php moodtpl_add_comment() ?>').'</pre>
				<p>'.__('Affiche du code HTML contenant le formulaire d\'ajout. A utiliser <u>dans</u> le formulaires des commentaires', 'moodlight').'.</p>
				
				<pre style="font-weight: bold">'.htmlentities('<?php moodtpl_post(); ?>').' </pre>
				<p>'.__('Affiche une div contenant l\'humeur générale des commentaires d\'un post. A utiliser dans la boucles des articles ou dans un article seul', 'moodlight').'</p>
				
				<pre style="font-weight: bold">'.htmlentities('<?php moodtpl_comment() ?>').' </pre>
				<p>'.__('Affiche une div contenant l\'humeur générale d\'un commentaire d\'un post. A utiliser dans la boucles des commentaires', 'moodlight').'</p>
				
				<pre style="font-weight: bold">'.htmlentities('<?php moodtpl_post_color(); ?>').' </pre>
				<p>'.__('Affiche une chaine de caractère contenant la couleur de l\'humeur de l\'article. A utiliser dans la boucles des articles ou dans un article seul', 'moodlight').'.</p>
				
				<pre style="font-weight: bold">'.htmlentities('<?php moodtpl_post_note() ?>').' </pre>
				<p>'.__('Affiche la notation de l\'article sous forme purement textuelle. A utiliser dans la boucles des articles ou dans un article seul', 'moodlight').'.</p>
				
				<pre style="font-weight: bold">'.htmlentities('<?php moodtpl_comment_color() ?>').' </pre>
				<p>'.__('Affiche une chaine de caractère contenant la couleur de l\'humeur du commentaire. A utiliser dans la boucles des commentaires', 'moodlight').'</p>
							
				<pre style="font-weight: bold">'.htmlentities('<?php moodtpl_post_chart() ?>').'</pre>
				<p>'.__('Affiche un graphique représentant les humeurs des commentaires', 'moodlight').'</p>
				<br />
				<h3>'.__('Adresse de support', 'moodlight').'</h3>
				&nbsp;&nbsp;&nbsp;&nbsp;&raquo; <a href="http://www.boolean.me/wp-moodlight">'.__('Support Moodlight', 'moodlight').'</a>
		';
		
		$current = array();
		if ($_GET['a'] == 'settings' || !$_GET['a'])  {
			$page_to_load = $page_configuration;
			$current['settings'] = 'class="current"';
		} else if ($_GET['a'] == 'doc') {
			$page_to_load = $page_doc;
			$current['doc'] = 'class="current"';
		} else if ($_GET['a'] == 'stats') {
			$page_to_load = $page_stats;
			$current['stats'] = 'class="current"';
		} else if ($_GET['a'] == 'active') {
			$page_to_load = $page_active;
			$current['active'] = 'class="current"';
		}
		
		echo '
			<div class="wrap">
				<div id="icon-options-general" class="icon32"></div>
			
				<h2>'.__('Configuration de Moodlight', 'moodlight').'</h2>
				<ul class="subsubsub">
					<li>
						<a '.$current['settings'].' href="options-general.php?page=moodlight/moodlight.php&a=settings">'.__('Configuration', 'moodlight').'</a>
					</li>
					|
					<li>
						<a '.$current['active'].' href="options-general.php?page=moodlight/moodlight.php&a=active">'.__('Activation par article', 'moodlight').'</a>
					</li>
					|
					<li>
						<a '.$current['stats'].' href="options-general.php?page=moodlight/moodlight.php&a=stats">'.__('Statistiques', 'moodlight').'</a>
					</li>
					|
					<li>
						<a '.$current['doc'].' href="options-general.php?page=moodlight/moodlight.php&a=doc">'.__('Documentation', 'moodlight').'</a>
					</li>
				</ul>
				<br /><br />
				'.$page_to_load.'
			</div>
		';
	}
	
	function moodlight_init_l10n() {
		load_plugin_textdomain('moodlight', PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)), dirname(plugin_basename(__FILE__)));
	}

	/* Run ! */
	register_activation_hook(__FILE__,'moodlight_init');

	add_action ('init', 'moodlight_init_l10n');
	
	moodlight();
?>
