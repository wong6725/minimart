<?php 
if ( !defined("ABSPATH") ) exit;

if( ! $args['accordions'] ) return;
$id = ( $args['accordions'] )? $args['accordions'] : 'accordion';
?>

<div id="<?php echo $id ?>" class="accordion-container modalView">
<?php 
    foreach( $args['accordions'] as $title => $segment ): 
        $id = sanitize_title( $title );
?>
    <div class="accordion-item">
        <h5 class="item-title">
            <button class="accordion-button btn btn-link collapsed" data-toggle="collapse" data-target="#<?php echo $id; ?>" aria-expanded="true" aria-controls="<?php echo $id; ?>">
            View Reference Document: <?php echo $title; ?>
            </button>
        </h5>

        <div id="<?php echo $id; ?>" class="accordion-content collapse" aria-labelledby="headingOne" data-parent="#<?php echo $id ?>">
            <div class="card-body">
                <?php echo $segment; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>