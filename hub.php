<?php
/*
Plugin Name: Hub
Plugin URI: https://github.com/korylprince/wordpress-hub
Description: Hub for Wordpress
Version: 1.0 
Author: Kory Prince 
Author URI: http://unstac.tk/ 
License: GPL2
 */
/*  Copyright 2013 Kory Prince  (email : korylprince@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

wp_register_style('hub',plugins_url('css/hub.css', __FILE__));
wp_register_style('hub-uploader',plugins_url('css/uploader.css', __FILE__));
wp_register_script('hub',plugins_url('js/hub.js', __FILE__));
wp_register_script('hub-uploader',plugins_url('js/uploader.js', __FILE__));

add_action( 'wp_enqueue_scripts', 'hub_register_scripts' );

function hub_register_scripts() { 
    wp_enqueue_style('hub');
    wp_enqueue_script('jquery');
    wp_enqueue_script('hub');
}

function hub_register_admin_scripts() { 
    wp_enqueue_style('hub-uploader');
    wp_enqueue_script('jquery');
    wp_enqueue_script('hub-uploader');
}

add_action( 'add_meta_boxes', 'hub_add_meta_box' );
add_action( 'save_post', 'hub_save' );

function hub_add_meta_box() {
    $screens = array( 'post', 'page' );
    foreach ($screens as $screen) {
        add_meta_box(
            'hub',
            'Hub',
            'hub_create_meta_box',
            $screen
        );
    }
    add_action( 'admin_enqueue_scripts','hub_register_admin_scripts');
}

function hub_create_meta_box($post) {
    wp_nonce_field( plugin_basename( __FILE__ ), 'hub_nonce' );
    $id = $post->ID;
    $order = get_post_meta($id, '_hub_order',true);
    if (isset($_GET['hub_message']) && $_GET['hub_message'] == 1 ) {
        echo '<strong style="color:#f00;">Order must be an Integer!</strong><br />';
    }
    echo '<label for="_hub_order">Order:</label> <input type="text" id="_hub_order" name="_hub_order" value="'.esc_attr($order).'" size="25" /><br />';

        echo '<div id="hub_uploader" data-type="'.get_post_type($id).'">'
        .hub_create_image($id)
        .'</div>';
}

function hub_create_image ($id) {
    $image_id = get_post_meta($id, '_hub_image',true);
    if ($image_id) {
        return '<img id="hub_image" src="'.wp_get_attachment_url($image_id).'" data-id='.$image_id.' /><br />'
            .'<a id="hub_remove">Remove Hub Image</a>';
    }
    else {
        return '<a id="hub_add">Add Hub Image</a>';
    }

}

function hub_save( $post_id, $ajax = false ) {
    // First we need to check if the current user is authorised to do this action. 
    if ( 'page' == $_POST['post_type'] ) {
        if ( ! current_user_can( 'edit_page', $post_id ) )
            return;
    } else {
        if ( ! current_user_can( 'edit_post', $post_id ) )
            return;
    }

    // Secondly we need to check if the user intended to change this value.
    if ( ! isset( $_POST['hub_nonce'] ) || ! wp_verify_nonce( $_POST['hub_nonce'], plugin_basename( __FILE__ ) ) )
        return;

    //Thirdly check if trying to autosave
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
        return;

    // Finally we can save the value to the database
    $post_ID = $_POST['post_ID'];
    if (!$ajax) {
        //sanitize user input
        $order = sanitize_text_field( $_POST['_hub_order'] );
        if (is_numeric($order)) {
            $order = intval($order);
            add_post_meta($post_ID, '_hub_order', $order, true) or
                update_post_meta($post_ID, '_hub_order', $order);
        }
        elseif ($order == '') {
            add_post_meta($post_ID, '_hub_order', $order, true) or
                update_post_meta($post_ID, '_hub_order', $order);
        }
        else {
            add_filter('redirect_post_location','hub_invalid_order');
        }
    }
    else {
        $image_id = sanitize_text_field( $_POST['_hub_image'] );
        if ($image_id == -1) { $image_id = '';}
        add_post_meta($post_ID, '_hub_image', $image_id, true) or
        update_post_meta($post_ID, '_hub_image', $image_id);
    }
}

function hub_invalid_order($loc) {
    return add_query_arg('hub_message',1,$loc);
}

add_action('wp_ajax_set-hub-image','hub_save_image');

function hub_save_image() {
    hub_save($_POST['post_ID'],true);
    header('Content-type: text/json');
    echo json_encode(array('success'=>true,'data'=>hub_create_image($_POST['post_ID'])));
    die();
}

function hub_shortcode($attrs) {

    echo '
<div class="hub">
    <ul class="hub-slides">';
    
    $posts = new WP_Query( 'meta_key=_hub_image' );
    $posts = $posts->posts;
    $pages = new WP_Query( 'meta_key=_hub_image&post_type=page' );
    $pages = $pages->posts;
    foreach( array_merge($posts,$pages) as $post) {
        $id = $post->ID;
        //skip if empty
        if (get_post_meta($id,'_hub_image',true) == '') {continue;}
        echo '<li data-order="'.get_post_meta($id,'_hub_order',true).'"><a class="hub-link" href="'.get_permalink($id).'"><img src="'.wp_get_attachment_url(get_post_meta($id,'_hub_image',true), 'large' ).'" /></a></li>';

    }

    echo '</ul>
        <ul class="hub-titles">';
    foreach( array_merge($posts,$pages) as $post) {
        $id = $post->ID;
        //skip if empty
        if (get_post_meta($id,'_hub_image',true) == '') {continue;}
        echo '<li data-order="'.get_post_meta($id,'_hub_order',true).'"><span>'.get_the_title($id).'</span></li>';
    }
    echo '</ul></div>';

    echo '<script type="text/javascript">
    jQuery(window).load(function() {
        // imgs
        var li = jQuery(".hub-slides li");
        li.detach().sort(function(a,b){
            var ao = jQuery(a).data("order") || 1000;
            var bo = jQuery(b).data("order") || 1000;
            return ao-bo;
        });
        jQuery(".hub-slides").append(li);
        jQuery(".hub-slides").children().first().addClass("active");

        // titles
        li = jQuery(".hub-titles li");
        li.detach().sort(function(a,b){
            var ao = jQuery(a).data("order") || 1000;
            var bo = jQuery(b).data("order") || 1000;
            return ao-bo;
        });
        jQuery(".hub-titles").append(li);
        jQuery(".hub-titles").children().first().addClass("active");
        jQuery(".hub-titles").addClass("item-1");

        jQuery(".hub-titles li").each(function() {
            jQuery(this).click(function(){
                jQuery(".hub-titles li").removeClass("active");
                var idx = jQuery(".hub-titles li").index(this);
                jQuery(".hub-slides li").removeClass("active");
                jQuery(this).addClass("active");
                jQuery(".hub-slides li").eq(idx).addClass("active");
                jQuery(".hub-titles").removeClass().addClass("hub-titles item-"+(idx+1).toString());
            });
        });
    });
</script>';
}
add_shortcode('hub','hub_shortcode');

?>
