<?php 

if ( !defined("ABSPATH") )
    exit;

?>

<ul class="inner-tab nav nav-tabs" id="<?php echo ( $args['id'] )? $args['id'] : 'wcwhInnerTabs' ?>" role="tablist">
<?php
	if( $args['header'] )
	{
		echo '<li class="nav-item">';
		echo '<span class="nav-link">'.$args['header'].'</span>';
		echo '</li>';
	}

	$c = 0;
	if( $args['tabs'] )
	{
		$size = sizeof( $args['tabs'] ); $i = 0;
		foreach( $args['tabs'] as $key => $title )
		{	
			$i++;
			$params = $_GET;
			$params['section'] = $key;
			$url = admin_url( "admin.php".add_query_arg( $params, '' ) ); 
			$active = ( ( !isset( $_GET['section'] ) && $c == 0 ) || ( isset( $_GET['section'] ) && $key == $_GET['section'] ) )? 'active' : '';

			echo '<li class="nav-item">';
			echo ' <a class="nav-link '.$active.'" id="'.$key.'-tab" href="'.$url.'" title="'.$args['desc'][$key].'" >'.$title.'</a>';
			echo ( $args['isStep'] && $i < $size )? '<span>&nbsp;>&nbsp;</span>' : '';
			echo '</li>';
			$c++;
		}
	}
?>	
</ul>