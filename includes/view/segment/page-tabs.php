<?php 

if ( !defined("ABSPATH") )
    exit;

?>

<ul class="page-tab nav nav-tabs" id="<?php echo ( $args['id'] )? $args['id'] : 'wcwhTabs' ?>" role="tablist">
<?php
	$c = 0;
	if( $args['tabs'] )
	{
		$size = sizeof( $args['tabs'] ); $i = 0;
		foreach( $args['tabs'] as $key => $title )
		{	
			$i++;
			$params = $_GET;
			$params['tab'] = $key;
			$url = admin_url( "admin.php".add_query_arg( $params, '' ) ); 
			$active = ( ( !isset( $_GET['tab'] ) && $c == 0 ) || ( isset( $_GET['tab'] ) && $key == $_GET['tab'] ) )? 'active' : '';

			echo '<li class="nav-item">';
			echo ' <a class="nav-link '.$active.'" id="'.$key.'-tab" href="'.$url.'" title="'.$args['desc'][$key].'" >'.$title.'</a>';
			echo ( $args['isStep'] && $i < $size )? '<span>&nbsp;>&nbsp;</span>' : '';
			echo '</li>';
			$c++;
		}
	}
?>	
</ul>