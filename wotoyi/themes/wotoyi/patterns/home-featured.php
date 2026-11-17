<?php
/**
 * Title: Accueil - Véhicules à la une
 * Slug: wotoyi/home-featured
 * Categories: wotoyi-home
 * Inserter: yes
 * Description: Accueil - Véhicules à la une
 */
?>
<?php echo wotoyi_block_comment( 'listings', array( 'source' => 'featured', 'layout' => 'rail', 'id' => 'featured', 'eyebrow' => __( 'À la une', 'wotoyi' ), 'title' => __( 'Véhicules à la une', 'wotoyi' ), 'linkLabel' => __( 'Tout voir', 'wotoyi' ), 'limit' => 8 ) ); ?>
