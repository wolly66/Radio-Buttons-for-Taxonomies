<?php
/*
Author: Stephen Harris http://profiles.wordpress.org/stephenh1988/
Github: https://github.com/stephenh1988

This is a class implementation of the wp.tuts+ tutorial: http://wp.tutsplus.com/tutorials/creative-coding/how-to-use-radio-buttons-with-taxonomies/

To use it, just add to your functions.php and add the javascript file to your theme’s js folder (call it radiotax.js).

Better still, make make a plug-in out of it, including the javascript file, and being sure to point the wp_register_script to radiotax.js in your plug-in folder.

The class constants are
  - taxonomy: the taxonomy slug
  - taxonomy_metabox_id: the ID of the original taxonomy metabox
  - post type - the post type the metabox appears on
*/

class WordPress_Radio_Taxonomy {
	private $taxonomy = '';
	private $taxonomy_metabox_id = '';
	private $post_type= '';

	public function __construct(){

		$this->taxonomy = 'mytaxonomy';

		$this->post_type = 'post';

		$this->taxonomy_metabox_id = 'mytaxonomydiv';

		//Remove old taxonomy meta box
		add_action( 'admin_menu', array( $this,'remove_meta_box'));

		//Add new taxonomy meta box
		add_action( 'add_meta_boxes', array( $this,'add_meta_box'));

		//Load admin scripts
		//add_action('admin_enqueue_scripts',array( $this,'admin_script'));

		//Load admin scripts
		add_action('wp_ajax_radio_tax_add_taxterm',array( $this,'ajax_add_term'));

	}

	public function remove_meta_box(){
   		remove_meta_box( $this->taxonomy_metabox_id, $this->post_type, 'normal');
	}


	public function add_meta_box() {
		add_meta_box( 'slownews_sections', 'Sections',array( $this,'metabox'), $this->post_type ,'side','core');
	}


	//Callback to set up the metabox
	public function metabox( $post ) {
		//Get taxonomy and terms


       	 //Set up the taxonomy object and get terms
       	 $tax = get_taxonomy($this->taxonomy);
       	 $terms = get_terms($this->taxonomy,array('hide_empty' => 0));

       	 //Name of the form
       	 $name = 'tax_input[' . $this->taxonomy . ']';

       	 //Get current and popular terms
       	 $popular = get_terms( $this->taxonomy, array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 10, 'hierarchical' => false ) );
       	 $postterms = get_the_terms( $post->ID,$this->taxonomy );
       	 $current = ($postterms ? array_pop($postterms) : false);
       	 $current = ($current ? $current->term_id : 0);
       	 ?>

		<div id="taxonomy-<?php echo $this->taxonomy; ?>" class="categorydiv">
			<!-- Display tabs-->
			<ul id="<?php echo $this->taxonomy; ?>-tabs" class="category-tabs">
				<li class="tabs"><a href="#<?php echo $this->taxonomy; ?>-all" tabindex="3"><?php echo $tax->labels->all_items; ?></a></li>
				<li class="hide-if-no-js"><a href="#<?php echo $this->taxonomy; ?>-pop" tabindex="3"><?php _e( 'Most Used' ); ?></a></li>
			</ul>

			<!-- Display taxonomy terms -->
			<div id="<?php echo $this->taxonomy; ?>-all" class="tabs-panel">
				<ul id="<?php echo $this->taxonomy; ?>checklist" class="list:<?php echo $this->taxonomy?> categorychecklist form-no-clear">
				<?php foreach($terms as $term){
       				 $id = $this->taxonomy.'-'.$term->term_id;
					$value= (is_taxonomy_hierarchical($this->taxonomy) ? "value='{$term->term_id}'" : "value='{$term->term_slug}'");
				        echo "<li id='$id'><label class='selectit'>";
				        echo "<input type='radio' id='in-$id' name='{$name}'".checked($current,$term->term_id,false)." {$value} />$term->name<br />";
				        echo "</label></li>";
		       	 }?>
				</ul>
			</div>

			<!-- Display popular taxonomy terms -->
			<div id="<?php echo $this->taxonomy; ?>-pop" class="tabs-panel" style="display: none;">
				<ul id="<?php echo $this->taxonomy; ?>checklist-pop" class="categorychecklist form-no-clear" >
				<?php foreach($popular as $term){
				        $id = 'popular-'.$this->taxonomy.'-'.$term->term_id;
					$value= (is_taxonomy_hierarchical($this->taxonomy) ? "value='{$term->term_id}'" : "value='{$term->term_slug}'");
				        echo "<li id='$id'><label class='selectit'>";
				        echo "<input type='radio' id='in-$id'".checked($current,$term->term_id,false)." {$value} />$term->name<br />";
				        echo "</label></li>";
				}?>
				</ul>
			</div>

			 <p id="<?php echo $this->taxonomy; ?>-add" class="">
				<label class="screen-reader-text" for="new<?php echo $this->taxonomy; ?>"><?php echo $tax->labels->add_new_item; ?></label>
				<input type="text" name="new<?php echo $this->taxonomy; ?>" id="new<?php echo $this->taxonomy; ?>" class="form-required form-input-tip" value="<?php echo esc_attr( $tax->labels->new_item_name ); ?>" tabindex="3" aria-required="true"/>
				<input type="button" id="" class="radio-tax-add button" value="<?php echo esc_attr( $tax->labels->add_new_item ); ?>" tabindex="3" />
				<?php wp_nonce_field( 'radio-tax-add-'.$this->taxonomy, '_wpnonce_radio-add-tag', false ); ?>
			</p>
		</div>
        <?php
    }


	public function ajax_add_term(){

		$taxonomy = !empty($_POST['taxonomy']) ? $_POST['taxonomy'] : '';
		$term = !empty($_POST['term']) ? $_POST['term'] : '';
		$tax = get_taxonomy($this->taxonomy);

		check_ajax_referer('radio-tax-add-'.$this->taxonomy, '_wpnonce_radio-add-tag');

		if(!$tax || empty($term))
			exit();

		if ( !current_user_can( $tax->cap->edit_terms ) )
			die('-1');

		$tag = wp_insert_term($term, $this->taxonomy);

		if ( !$tag || is_wp_error($tag) || (!$tag = get_term( $tag['term_id'], $this->taxonomy )) ) {
			//TODO Error handling
			exit();
		}

		$id = $this->taxonomy.'-'.$tag->term_id;
		$name = 'tax_input[' . $this->taxonomy . ']';
		$value= (is_taxonomy_hierarchical($this->taxonomy) ? "value='{$tag->term_id}'" : "value='{$term->tag_slug}'");

		$html ='<li id="'.$id.'"><label class="selectit"><input type="radio" id="in-'.$id.'" name="'.$name.'" '.$value.' />'. $tag->name.'</label></li>';

		echo json_encode(array('term'=>$tag->term_id,'html'=>$html));
		exit();
	}

}
$wordpress_radio_taxonomy = new WordPress_Radio_Taxonomy();