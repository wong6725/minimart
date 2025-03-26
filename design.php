<?php
if ( !defined( "ABSPATH" ) )
    exit;
	
if ( !class_exists( "WCWH_Design" ) )
{

class WCWH_Design
{
	protected $refs;
	
	protected $settings;
	
	public function __construct( $refs )
	{
		global $wcwh;
        $this->refs = ( $refs )? $refs : $wcwh->get_plugin_ref();
		$this->load_settings();
		
		add_action( 'login_head', array( $this, 'login_heading') );
		add_action( 'admin_head', array( $this, 'admin_heading') );
		
		add_filter( 'login_headerurl', array( $this, 'login_headerurl' ) );
		add_filter( 'login_headertext', array( $this, 'login_headertext' ) );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_logo'), 1 );
		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu'), 11 );
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 999 );
		add_filter( 'update_footer', array( $this, 'admin_footer_text' ), 999 );
		
		add_action( 'user_register', array( $this, 'set_default_admin_color' ) );
		add_action( 'personal_options_update', array( $this, 'set_default_admin_color' ) );
		//remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
    }

    public function __destruct()
	{
		unset($this->refs);
		unset($this->settings);
	}
	
	public function load_settings()
	{
		$this->settings = apply_filters( 'wcwh_design_setting', array(
				'strict' => false,
				'custom_style' => true,
				'custom_login_icon_url' => '',
				'off_login_backtoblog' => true,
				'off_login_remember_me' => true,
				'off_login_lostpassword' => true,
				'off_menu_screen_options' => true,
				'off_menu_help' => true,
				'off_update_nag' => true,
				'off_admin_bar_comment' => true,
				'off_admin_bar_update' => true,
				'off_admin_bar_site_link' => true,
				'off_admin_bar_multisite' => true,
				'off_admin_bar_header_logo' => true,
				'off_admin_bar_header_context_menu' => true,
				'off_admin_bar_header_new_content' => true,
				'admin_bar_off_greet' => true,
				'admin_bar_howdy' => 'Hello',
				'admin_bar_full_logo_url' => '',
				'admin_bar_logo_url' => '',
				'off_footer' => true, 
				'color_scheme' => 'light',
			) 
		);
		
		return $this->settings;
	}
	
	public function get_settings()
	{
		return ( $this->settings )? $this->settings : $this->load_settings();
	}
	
	public function login_heading()
	{
		$setting = $this->get_settings();
	?>
		<style type="text/css">
			<?php if( $setting['custom_style'] ): ?>
				#login
				{
					width:380px;
				}
				#loginform #wp-submit
				{
					float:none;width:100%;font-size:24px;height:auto;padding:5px;line-height:1.3;margin-top:20px;
				}
				#login form
				{
					border-radius:3px;
				}
				#login .message
				{
					border-radius:1px;
				}
			<?php endif; ?>
			
			<?php if( !empty( $setting['custom_login_icon_url'] ) ): ?>
				.login h1 a
				{ 
					background-image:none,url(<?php echo $setting['custom_login_icon_url'] ?>);
					background-size:contain;
					height:64px;
					width:auto;
					pointer-events: none;
				}
			<?php else: ?>
				.login h1 a
				{ 
					background-image:none;
					pointer-events: none;
				}
			<?php endif; ?>
			
			<?php if( $setting['off_login_backtoblog'] ): ?>
				#backtoblog
				{
					display:none;
				}
            <?php endif; ?>
			
			<?php if( $setting['off_login_remember_me'] ): ?>
				#loginform .forgetmenot
				{
					display:none;
				}
			<?php endif; ?>
			
			<?php if( $setting['off_login_lostpassword'] ): ?>
				#login p#nav
				{
					display:none;
				}
			<?php endif; ?>
		</style>
	<?php
	}
	
		public function no_update_nag()
		{
			remove_action( 'admin_notices', 'no_update_nag', 3 );
		}
	
	public function admin_heading()
	{
		$setting = $this->get_settings();
		//print_r($setting);
	?>
		<style type="text/css">
			<?php if( $setting['custom_style'] ): ?>
				html.wp-toolbar
				{
					padding-top:50px;
				}
				#wpwrap
				{
					background:#F2F7F8;
				}
				
				/* Admin Bar Left */
				#wpadminbar
				{
					height:50px;background:linear-gradient(to right, #0178bc 0%, #00bdda 100%);
					box-shadow:0 1px 4px 0 rgba(0,0,0,.1),0 3px 10px 0 rgba(0,0,0,.09);
					display:flex;flex-direction:column;justify-content:center;z-index:1000;
				}
				#wpadminbar .ab-top-menu
				{
					display:flex;flex-direction:row;justify-content:space-between;align-items:center;
				}
				#wpadminbar .quicklinks .ab-empty-item
				{
					height:50px;line-height:50px;padding:0;
				}
				#wpadminbar #wp-admin-bar-main-logo .main-logo
				{
					background:#fff;height:50px;width:180px;
					display:flex;flex-direction:row;justify-content:center;align-items:center;
				}
				.folded #wpadminbar #wp-admin-bar-main-logo .main-logo
				{
					width:36px;
				}
				#wpadminbar #wp-admin-bar-main-logo img.full-logo
				{
					height:36px;
				}
				#wpadminbar .main-logo img.half-logo
				{
					width:30px;display:none;
				}
				.folded #wpadminbar .main-logo img.half-logo
				{
					display:initial;
				}
				.folded #wpadminbar .main-logo img.full-logo
				{
					display:none;
				}
				#wpadminbar .ab-item, #wpadminbar a.ab-item, #wpadminbar > #wp-toolbar span.ab-label, #wpadminbar > #wp-toolbar span.noticon
				{
					color:#fff;
				}
				#wpadminbar .ab-icon, #wpadminbar .ab-icon::before, #wpadminbar .ab-item::after, #wpadminbar .ab-item::before
				{
					color:#fff !important;
				}
				#wpadminbar .ab-top-menu > li.hover > .ab-item, 
				#wpadminbar .ab-top-menu > li.menupop.hover > .ab-item, 
				#wpadminbar .ab-top-menu > li:hover > .ab-item, 
				#wpadminbar .ab-top-menu > li > .ab-item:focus, 
				#wpadminbar.nojq .quicklinks .ab-top-menu > li > .ab-item:focus, 
				#wpadminbar.nojs .ab-top-menu > li.menupop:hover > .ab-item, 
				#wpadminbar > #wp-toolbar > #wp-admin-bar-root-default li:hover span.ab-label, 
				#wpadminbar > #wp-toolbar > #wp-admin-bar-top-secondary li.hover span.ab-label
				{
					color:#fff !important;
				}
				#wpadminbar .ab-top-menu > li.menupop.hover > .ab-item, 
				#wpadminbar.nojq .quicklinks .ab-top-menu > li > .ab-item:focus, 
				#wpadminbar.nojs .ab-top-menu > li.menupop:hover > .ab-item, 
				#wpadminbar:not(.mobile) .ab-top-menu > li:hover > .ab-item, 
				#wpadminbar:not(.mobile) .ab-top-menu > li > .ab-item:focus
				{
					background:none;
				}
				#wpadminbar #wp-admin-bar-main-menu-title
				{ 
					padding:5px 30px; 
				}
				#wpadminbar #wp-admin-bar-main-menu-title span
				{ 
					font-size:20px;
				}
				
				/* Admin Bar Right */
				#wpadminbar #wp-admin-bar-my-account.with-avatar > .ab-empty-item img, 
				#wpadminbar #wp-admin-bar-my-account.with-avatar > a img
				{
					height:30px;border-radius:50%;
				}
				#wpadminbar ul.ab-top-secondary li .ab-item
				{
					height:50px;display:flex;flex-direction:column;justify-content:center;
				}
				#wpadminbar:not(.mobile) .ab-top-menu > li#wp-admin-bar-main-logo:hover > .ab-item
				{
					background:none;
				}
				#wpadminbar:not(.mobile) .ab-top-menu > li:hover > .ab-item,
				#wpadminbar:not(.mobile) .ab-top-menu > li > .ab-item:focus
				{
					background:none;
				}
				#wp-admin-bar-user-info .avatar
				{
					border-radius:50%;
				}
				#wpadminbar #wp-admin-bar-menu-toggle a
				{
					height:44px;
				}
				#wpadminbar #wp-admin-bar-sites > .ab-item::before
				{
					content:"\f541";
					top:2px;
				}
				
				/* Side Menu */
				#wp-toolbar
				{
					display:flex;flex-direction:row;justify-content:space-between;
				}
				#adminmenu
				{
					margin:0; margin-bottom:36px;
				}
				#adminmenuback
				{
					box-shadow:1px 0px 15px rgba(0, 0, 0, 0.08);
				}
				#adminmenuwrap
				{
					z-index:999;
				}
				#adminmenu, #adminmenu .wp-submenu, #adminmenuback, #adminmenuwrap
				{
					width:180px;
				}
				#adminmenu, #adminmenuback, #adminmenuwrap
				{
					background:#fff;
				}
				#adminmenu .wp-has-current-submenu .wp-submenu a, 
				#adminmenu .wp-has-current-submenu.opensub .wp-submenu a, 
				#adminmenu a.wp-has-current-submenu:focus + .wp-submenu a
				{
					margin-left:32px;
				}
				.folded #adminmenu .wp-has-current-submenu .wp-submenu a, 
				.folded #adminmenu .wp-has-current-submenu.opensub .wp-submenu a,
				.folded#adminmenu a.wp-has-current-submenu:focus + .wp-submenu a
				{
					margin-left:0px;
				}
				#adminmenu li.wp-menu-separator
				{
					display:none;
				}
				#adminmenu .wp-submenu
				{
					left:180px;
				}
				#collapse-menu
				{
					position:fixed;z-index:999;bottom:0;left:0;width:180px;height:36px;background:#fff;
					border-top:1px solid rgba(120, 130, 140, 0.13);
				}
				#collapse-button .collapse-button-label
				{
					top:4px;
				}
				.folded #collapse-menu
				{
					width:36px;
				}
				#collapse-button:focus, #collapse-button:hover
				{
					color:#007bff;
				}
				#adminmenu a:hover, #adminmenu li.menu-top:hover, 
				#adminmenu li.opensub > a.menu-top, 
				#adminmenu li > a.menu-top:focus
				{
					background:rgba(0,0,0,0);
				}
				#adminmenu a:hover, 
				#adminmenu li.menu-top:hover, 
				#adminmenu li.opensub > a.menu-top, 
				#adminmenu li > a.menu-top:focus
				{
					color:#007bff;
				}
				#adminmenu li.menu-top:hover div.wp-menu-image:before,
				#adminmenu li.opensub>a.menu-top div.wp-menu-image:before
				{
					color:#007bff;
				}
				#adminmenu li.current a.menu-top, 
				#adminmenu li.wp-has-current-submenu .wp-submenu .wp-submenu-head, 
				#adminmenu li.wp-has-current-submenu a.wp-has-current-submenu, 
				.folded #adminmenu li.current.menu-top,
				.folded #adminmenu li.wp-has-current-submenu
				{
					background:rgba(0,0,0,0);color:#007bff;
				}
				#adminmenu a.current:hover div.wp-menu-image:before,
				#adminmenu .current div.wp-menu-image::before,
				#adminmenu li a:focus div.wp-menu-image:before,
				#adminmenu li.opensub div.wp-menu-image:before,
				#adminmenu li.wp-has-current-submenu a:focus div.wp-menu-image:before,
				#adminmenu li.wp-has-current-submenu div.wp-menu-image:before,
				#adminmenu li.wp-has-current-submenu.opensub div.wp-menu-image:before,
				#adminmenu li:hover div.wp-menu-image:before,
				.ie8 #adminmenu li.opensub div.wp-menu-image:before 
				{
					color:#007bff
				}
				#adminmenu .wp-has-current-submenu .wp-submenu a:focus,
				#adminmenu .wp-has-current-submenu .wp-submenu a:hover,
				#adminmenu .wp-has-current-submenu.opensub .wp-submenu a:focus,
				#adminmenu .wp-has-current-submenu.opensub .wp-submenu a:hover,
				#adminmenu .wp-submenu a:focus,
				#adminmenu .wp-submenu a:hover,
				#adminmenu a.wp-has-current-submenu:focus+.wp-submenu a:focus,
				#adminmenu a.wp-has-current-submenu:focus+.wp-submenu a:hover,
				.folded #adminmenu .wp-has-current-submenu .wp-submenu a:focus,
				.folded #adminmenu .wp-has-current-submenu .wp-submenu a:hover 
				{
					color:#007bff
				}
				#adminmenu .wp-has-current-submenu.opensub .wp-submenu li.current a:focus,
				#adminmenu .wp-has-current-submenu.opensub .wp-submenu li.current a:hover,
				#adminmenu .wp-submenu li.current a:focus,
				#adminmenu .wp-submenu li.current a:hover,
				#adminmenu a.wp-has-current-submenu:focus+.wp-submenu li.current a:focus,
				#adminmenu a.wp-has-current-submenu:focus+.wp-submenu li.current a:hover 
				{
					color:#007bff
				}
				ul#adminmenu a.wp-has-current-submenu:after,
				ul#adminmenu>li.current>a.current:after 
				{
					border-right-color:#007bff
				}
				#adminmenu li.menu-top.wp-menu-open
				{
					border-top:1px solid rgba(120, 130, 140, 0.13);border-bottom:1px solid rgba(120, 130, 140, 0.13);
				}
				#adminmenu .awaiting-mod, 
				#adminmenu .update-plugins,
				#adminmenu li a.wp-has-current-submenu .update-plugins, 
				#adminmenu li.current a .awaiting-mod, 
				#adminmenu li.menu-top:hover > a .update-plugins, 
				#adminmenu li:hover a .awaiting-mod
				{
					background:#d64e07;color:#fff;
				}
				
				/* Content */
				#wpbody-content
				{
					padding:8px;margin:12px 0;border-radius:3px;
				}
				.wrap
				{
					margin:0;
				}
				.wrap h1, .wrap h2, .wrap h3{border-bottom:1px solid rgba(120, 130, 140, 0.13);}
				#wpcontent, #wpfooter
				{
					margin-left:180px;margin-right:16px;
				}
				.folded #wpcontent, .folded #wpfooter
				{
					margin-right:16px;
				}

				/* User Profile */
				.user-rich-editing-wrap, .user-syntax-highlighting-wrap, .user-admin-color-wrap, 
				.user-comment-shortcuts-wrap, .show-admin-bar.user-admin-bar-front-wrap, .user-url-wrap
				{
					display:none;
				}

				/* Others */
				.woocommerce-layout__header
				{
					top:50px;
				}
				.woocommerce-layout__header
				{
					z-index:999;
				}
				.woocommerce-layout__activity-panel
				{
					top:49px;
				}
				
				@media (max-width:960px)
				{
					.auto-fold #collapse-menu
					{
						width:36px
					}
					.auto-fold #wpadminbar #wp-admin-bar-main-logo .main-logo
					{
						width:36px;
					}
					.auto-fold #wpadminbar .main-logo img.half-logo
					{
						display:initial;
					}
					.auto-fold #wpadminbar .main-logo img.full-logo
					{
						display:none;
					}
				}
				@media (max-width:782px)
				{
					#wpadminbar .ab-top-secondary .menupop .ab-sub-wrapper
					{
						margin-top:0;
					}
					#wpadminbar li#wp-admin-bar-site-name
					{
						display:none;
					}
					#wpadminbar li#wp-admin-bar-menu-toggle
					{
						order:2;
					}
					#wpadminbar li#wp-admin-bar-main-logo
					{
						display:block;order:1;
					}
					.auto-fold #wpadminbar #wp-admin-bar-main-logo .main-logo
					{
						width:50px;
						height:48px;
					}
					#wpadminbar .ab-icon, #wpadminbar .ab-icon::before, #wpadminbar .ab-item::after, #wpadminbar .ab-item::before,
					#wpadminbar .ab-icon, #wpadminbar .ab-icon:hover, #wpadminbar .ab-icon, #wpadminbar .ab-icon:focus,
					#wpadminbar .ab-icon, #wpadminbar .ab-icon:visited,#wpadminbar .ab-icon, #wpadminbar .ab-icon:active,
					#wpadminbar .ab-icon, #wpadminbar .ab-icon:focus-within
					{
						color:#fff !important
					}
					.wp-responsive-open #wpadminbar #wp-admin-bar-menu-toggle a
					{
						background:#888;
					}
					.auto-fold #adminmenu a.menu-top
					{
						height:auto;
					}
					.wp-responsive-open #wpbody
					{
						right:0;
					}
					#wpcontent, #wpfooter
					{
						margin-right:0;
					}
					.folded #wpcontent, .folded #wpfooter
					{
						margin-right:0px;
					}
					.auto-fold #wpcontent
					{
						padding-left:0;
					}
					#wpwrap.wp-responsive-open
					{
						overflow:hidden;
					}
				}
				@media (max-width:600px)
				{
					html.wp-toolbar
					{
						padding-top:0;
					}
				}
			<?php endif; ?>
			
			<?php if( $setting['off_menu_screen_options'] && !current_user_can( 'manage_options' ) || $setting['strict'] ): ?>
				#screen-options-link-wrap
				{
					display:none;
				}
            <?php endif; ?>
			
			<?php if( $setting['off_menu_help'] && !current_user_can( 'manage_options' ) || $setting['strict'] ): ?>
				#contextual-help-link-wrap
				{
					display:none;
				}
				#contextual-help-link
				{
					display:none;
				}
            <?php endif; ?>
			
			<?php if( $setting['off_update_nag'] ): ?>
            <?php add_action( 'admin_init', array( $this, 'no_update_nag' ) ); ?>
				#update-nag
				{
					display:none;
				}
				.update-nag
				{
					display:none;
				}
            <?php endif; ?>
			
			<?php if( $setting['off_admin_bar_comment'] && !current_user_can( 'manage_options' ) || $setting['strict'] ): ?>
				ul#wp-admin-bar-root-default li#wp-admin-bar-comments
				{
					display:none;
				}
			<?php endif; ?>
			
			<?php if( $setting['off_admin_bar_update'] && !current_user_can( 'manage_options' ) || $setting['strict'] ): ?>
				ul#wp-admin-bar-root-default li#wp-admin-bar-updates
				{
					display:none;
				}
			<?php endif; ?>
			
			<?php if( $setting['off_admin_bar_site_link'] && !current_user_can( 'manage_options' ) || $setting['strict'] ): ?>
				#wp-admin-bar-site-name
				{
					display:none;
				}
			<?php endif; ?>

			<?php if( $setting['off_admin_bar_multisite'] && !current_user_can( 'manage_options' ) || $setting['strict'] ): ?>
				#wp-admin-bar-my-sites
				{
					display:none;
				}
			<?php endif; ?>
			
			<?php if( $setting['off_admin_bar_header_logo'] && !current_user_can( 'manage_options' ) || $setting['strict'] ): ?>
				#wphead #header-logo
				{
					display:none;
				}
				ul#wp-admin-bar-root-default li#wp-admin-bar-wp-logo
				{
					display:none;
				}
			<?php endif; ?>
			
			<?php if( $setting['off_admin_bar_header_context_menu'] && !current_user_can( 'manage_options' ) || $setting['strict'] ): ?>
				#wpadminbar #wp-admin-bar-root-default > #wp-admin-bar-wp-logo .ab-sub-wrapper
				{
					display:none;
				}
				#wpadminbar #wp-admin-bar-root-default > #wp-admin-bar-site-name .ab-sub-wrapper
				{
					display:none;
				}
			<?php endif; ?>
			
			<?php if( $setting['off_admin_bar_header_new_content'] && !current_user_can( 'manage_options' ) || $setting['strict'] ): ?>
				ul#wp-admin-bar-root-default li#wp-admin-bar-new-content
				{
					display:none;
				}
			<?php endif; ?>

			#wpbody-content #dashboard-widgets #postbox-container-1
			{
				width: 100%;
			}
		</style>
		<script type="text/javascript">
            /* <![CDATA[ */
            jQuery(document).ready( function()
            {
                try
                {
					
					
					

                }catch(err)
                {
                    errors = "ADMIN DESIGN CHANGE ERROR: " + err.name + " / " + err.message;
                    console.log(errors);
                }
            });
            /* ]]> */
        </script>
	<?php
	}
	
	public function login_headerurl( $url )
	{
		$setting = $this->get_settings();
		return ( $setting['login_headerurl'] )? $setting['login_headerurl'] : $url;
	}
	
	public function login_headertext( $text )
	{
		$setting = $this->get_settings();
		return ( $setting['login_headertext'] )? $setting['login_headertext'] : $text;
	}
	
	public function admin_bar_logo( $wp_admin_bar ) 
	{
		if ( ! is_admin() || ! is_admin_bar_showing() ) return;

		$wp_admin_bar->add_node(
			array(
				'parent' => '',
				'id'     => 'main-menu-title',
				'title'  => '<span class="collapse-button-icon" aria-hidden="true">'.get_bloginfo( 'name' ).'</span>',
			)
		);

		$setting = $this->get_settings();
		if( empty( $setting['admin_bar_logo_url'] ) ) return;
		$wp_admin_bar->add_node(
			array(
				'parent' => '',
				'id'     => 'main-logo',
				'title'  => '<div class="main-logo"><img class="full-logo" src="'.$setting['admin_bar_full_logo_url'].'"><img class="half-logo" src="'.$setting['admin_bar_logo_url'].'"></div>',
			)
		);
	}
	
	public function admin_bar_menu( $wp_admin_bar ) 
	{
        $setting = $this->get_settings();
		
		if( !empty( $setting['admin_bar_howdy'] ) )
		{
            $user_id = get_current_user_id();
            $current_user = wp_get_current_user();
            $profile_url = get_edit_profile_url( $user_id );

            if ( 0 != $user_id ) 
            {
                /* Add the "My Account" menu */
                $avatar = get_avatar( $user_id, 28 );
                $howdy = sprintf( __( $setting['admin_bar_howdy'].', %1$s'), $current_user->display_name );
                $class = empty( $avatar ) ? '' : 'with-avatar';

                $wp_admin_bar->add_menu( array(
                    'id' => 'my-account',
                    'parent' => 'top-secondary',
                    'title' => ( $setting['admin_bar_off_greet']? '' : $howdy ) . $avatar,
                    'href' => $profile_url,
                    'meta' => array(
                        'class' => $class,
                    ),
                ) );
            }
        }
    }
	
	public function admin_footer_text( $text )
	{
		$setting = $this->get_settings();
		return $setting['off_footer']? '' : $text;
	}
	
	public function set_default_admin_color( $user_id ) 
	{
		$setting = $this->get_settings();
		update_user_meta( $user_id, 'admin_color', $setting['color_scheme'] );
	}
}

new WCWH_Design( $refs );

}