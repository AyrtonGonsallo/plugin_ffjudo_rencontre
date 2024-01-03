<?php
class ffjudo_rencontre_admin {

	public $post_type_rencontre;
	public $post_type_equipe;
  public $post_type_judoka;
	private $parent;

	function __construct($parent) {
			$this->parent=$parent;
			$this->post_type_rencontre=$parent->post_type_rencontre;
			$this->post_type_equipe=$parent->post_type_equipe;
      $this->post_type_judoka=$parent->post_type_judoka;

		/*
     ajoute des colonnes a la liste des equipes
    */
    add_filter( 'manage_edit-'.$this->post_type_equipe.'_columns', array( $this, 'eq_list_columns' ),10 );
    add_action( 'manage_'.$this->post_type_equipe.'_posts_custom_column', array( $this, 'eq_list_render_columns' ),10,2 );

    /*
     ajoute des colonnes a la liste des rencontres
    */
    add_filter( 'manage_edit-'.$this->post_type_rencontre.'_columns', array( $this, 're_list_columns' ),10 );
    add_action( 'manage_'.$this->post_type_rencontre.'_posts_custom_column', array( $this, 're_list_render_columns' ),10,2 );

    /*
     ajoute des colonnes a la liste des judokas
    */
    add_filter( 'manage_edit-'.$this->post_type_judoka.'_columns', array( $this, 'ju_list_columns' ),10 );
    add_action( 'manage_'.$this->post_type_judoka.'_posts_custom_column', array( $this, 'ju_list_render_columns' ),10,2 );
	}


  function re_list_columns($columns)
  {
    $columns['statut'] = 'Statut';
    $columns['equipe_1'] = 'équipe 1';
    $columns['equipe_2'] = 'équipe 2';
    $columns['url_flux'] = 'json';
    return $columns;
  }
  function re_list_render_columns( $column,$post_id )
  {
    global $post;

    switch ( $column )
    {
      case 'url_flux':
      $url=get_field($column,$post_id);
      echo '<abbr title="'.esc_attr($url).'">'.basename($url).'</abbr>';
      break;

      case 'statut':
      $v=get_field($column,$post_id);
      if(is_array($v)) echo $v['label'];
      else echo $v;
      break;

      case 'equipe_1':
      case 'equipe_2':
      $tab=get_field($column,$post_id);
      if(is_array($tab)){
        $e_id=current($tab);
        echo $e_id->post_title;
      }
      break;


      default:
      break;
    }
  }

	function eq_list_columns($columns)
  {
    $columns['ffj_nom'] = 'Nom ffj';
    return $columns;
  }

  function eq_list_render_columns( $column,$post_id )
  {
    global $post;

    switch ( $column )
    {
      case 'ffj_nom':
      echo get_field($column,$post_id);
      break;

    	default:
    	break;
    }
  }

  function ju_list_columns($columns)
  {
    $columns['id_ffjda'] = 'ID ffj';
    return $columns;
  }

  function ju_list_render_columns( $column,$post_id )
  {
    global $post;

    switch ( $column )
    {
      case 'id_ffjda':
      echo get_field($column,$post_id);
      break;

      default:
      break;
    }
  }

	function debug($val)
	{
    $this->parent->debug('admin:'.print_r($val,true));
	}
}