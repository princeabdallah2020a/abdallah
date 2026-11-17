<?php
/**
 * Title: Accueil - Locations
 * Slug: wotoyi/home-rentals
 * Categories: wotoyi-home
 * Inserter: yes
 * Description: Accueil - Locations
 */
?>
<?php echo wotoyi_block_comment( 'listings', array( 'source' => 'rental', 'layout' => 'grid', 'id' => 'rental', 'eyebrow' => __( 'Location', 'wotoyi' ), 'title' => __( 'À louer maintenant', 'wotoyi' ), 'linkLabel' => __( 'Toutes les locations', 'wotoyi' ), 'limit' => 6 ) ); ?>
