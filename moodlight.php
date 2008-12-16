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
	 * @version 1.0.2
	 */
	/*
		Plugin Name: moodlight
		Plugin URI: http://www.boolean.me/2008/12/12/moodlight-plugin-wordpress/
		Description: Moodlight allows your visitors to add their mood on posts via comments. 
		Author: Gasquez Florian
		Version: 1.0.2
		Author URI: http://www.boolean.me
	*/
	
	$moodlight_version = "1.0.2";
	$moodlight_comments = NULL;

	/* Install the moodlight plugin */
	function moodlight_install()
	{
		global $wpdb, $moodlight_version;
		
		$table_name = $wpdb->prefix . 'moodlight';
		
		if ($wpdb->get_var('show table like '.$table_name) != $table_name) {
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
			
			if (!get_option('moodlight_version')) {
				add_option("moodlight_version", $moodlight_version);
			} else {
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

		
		if (in_array($note, $exist)) {
			$insert = '
				INSERT INTO '.$table_name.'(id_post, id_comment, moodlight)
				VALUES ('.$id_post.', '.$id_comment.', "'.$note.'")
			';
			
			$wpdb->query($insert);
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
			SELECT '.$wpdb->prefix.'posts.ID, post_title FROM '.$wpdb->prefix.'posts, '.$wpdb->prefix.'moodlight WHERE '.$wpdb->prefix.'moodlight.id_post='.$wpdb->prefix.'posts.ID GROUP BY '.$wpdb->prefix.'posts.ID
		';
	
		$posts = $wpdb->get_results($query, OBJECT);
		
		return $posts;
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
	function moodtpl_post_note()
	{
		$result = moodlight_mood();
		$color = $result[0];

		$n = get_option('moodlight_n_color');
		
		if (!$n) {
			$n = 2;
		}
		
		echo $result[1].'/'.$n;
	}
	
	/* tpl for post color */
	function moodtpl_post_color()
	{
		$result = moodlight_mood();
		$color = $result[0];

		$n = get_option('moodlight_n_color');
		
		if (!$n) {
			$n = 2;
		}
		
		echo 'rgb('.$color[0].', '.$color[1].', '.$color[2].')';
	}
	
	/* tpl for comment */
	function moodtpl_comment() {
		global $wpdb, $post, $comment, $moodlight_comments;
		
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
		$html = '
			<a href="http://www.boolean.me" style="display: none;" title="Merci de laisser ce lien pour les statistiques :)"><img src="http://www.boolean.me/counter.php" alt="statistiques" /></a>
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
		
		echo $html;
	}
	
	/* tpl for post percent, ty to http://www.cafe-froid.net/ */
	function moodtpl_post_percent()
	{
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
		
		if ($_POST['id_post']) {
			$post_title = $wpdb->get_var('SELECT post_title FROM '.$wpdb->prefix.'posts WHERE '.$wpdb->prefix.'posts.ID='.intval($_POST['id_post']));
			$page_stats .= '
				<h3>Stats de '.$post_title.'</h3>
				<p>'.__('Evolution de l\'humeur en fonction des commentaires', 'moodlight').'</p>
				'.moodlight_stats_id(intval($_POST['id_post'])).'
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
							
				<br />
				<h3>Adresse de support</h3>
				&nbsp;&nbsp;&nbsp;&nbsp;&raquo; <a href="http://www.boolean.me/2008/12/12/moodlight-plugin-wordpress/">'.__('Support Moodlight', 'moodlight').'</a>
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
		}
		
		echo '
			<div class="wrap">
				<div id="icon-options-general" class="icon32"></div>
			
				<h2>'.__('Configuration de Moodlight', 'moodlight').'</h2>
				<ul class="subsubsub">
					<li>
						<a '.$current['settings'].' href="options-general.php?page=moodlight/moodlight.php&a=settings">'.__('Configuration', 'moodlight').'</a>
					</a>
					|
					</li>
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