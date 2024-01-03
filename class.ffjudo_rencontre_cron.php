<?php

/**
 * lecture du flux json
 * sauvegarde dans la fiche rencontre
**/
class ffjudo_rencontre_cron extends plugin_base{

  /* cron */
  const SCHEDULE = 'ffj_download_json'; // nom de l'intervalle cron
  const SCHEDULE_INTERVAL = 60; // duree en secondes

  public $post_type_rencontre = 'rencontre';
  public $post_type_equipe = 'equipes';
  public $post_type_judoka = 'judoka';

  public $debug=1;

  function __construct()
  {
    $domain = parse_url(site_url(),PHP_URL_HOST);
    $this->def_options=array(
      'debug' => array (
        'active'    => $this->debug,
        'mail_dest' => 'zetoun.17@gmail.com',
        'mail_from' => 'ffjudo_rencontre_cron <noreply@'.$domain.'>',
      ),
      'mail_cron_run'=>true,
      'dest_cron_run'=>'zetoun.17@gmail.com',
    );

    $this->init(__FILE__); // init debug,plugin_dir_path, options
    $this->classname='ffjudo_rencontre_cron';
    $this->transient_name='ffjudo_rencontre_cron'; // utilisée par debug_late_save()

    if(is_admin())
    {
      require_once($this->plugin_dir_path.'admin/admin.php');
      $admin=new ffjudo_rencontre_admin($this);
    }
    else {

    }

    //add_action( 'rencontre-api_download_json', [$this,'download_json'],10,1 ); // action qui sera appellée par le cron

    //add_filter( 'cron_schedules', [$this,'cron_add_minute'] );
    //add_action( 'rencontre_cron', [$this,'cron_exec'] ); // action qui sera appellée par le cron
    //$this->update_cron();
    //$this->disable_cron();
    add_action( 'template_redirect', [$this,'trait_get_data'] );
  }

  function trait_get_data()
  {
    if(empty($_GET)) return;

    // declenche la mise a jour
    // appel effectué par un cron toutes les minutes depuis serveur o2switch
    // wget -O - -q 'https://judoproleague.webimedia.fr/?rencontre_dl_json=1' --user-agent="JPL/CRON" > /dev/null 2>&1
    if( !empty($_GET['rencontre_dl_json']) ) {
      $val = filter_input(INPUT_GET, 'rencontre_dl_json', FILTER_SANITIZE_URL);
      $force=($val==='force');
      $this->download_json($force,true);
      exit;
    }


  }

  /**
   *  active ou desactive la tache programmee
   * TODO : a supprimer
  **/
  function disable_cron() {
    $timestamp = wp_next_scheduled( 'rencontre_cron' );
    wp_unschedule_event( $timestamp, 'rencontre_cron' );
  }

  /**
   *  active la tache programmee si on a des rencontres non terminée et avec le flux json modifié sinon se desactive
   * TODO : a supprimer
  **/
  function update_cron()
  {
    $rencontres=$this->get_rencontre();
    if(is_array($rencontres) && count($rencontres))
    {
      if ( !wp_next_scheduled( 'rencontre_cron' ) )
      {
        wp_schedule_event( time(), self::SCHEDULE, 'rencontre_cron' );
        return;
      }
    }
    $this->disable_cron();

  }

  /**
   * determine la date de debut pour lire le json
   * lit toutes les fiches rencontre non terminée et avec un flux
   **/
  function get_rencontre()
  {
    $this->debug(__FUNCTION__);
    // lecture des rencontres avec un post_meta url_flux non vide
    $args=[
      'fields'      => 'ids',
      'numberposts' => -1,
      'post_type'   => $this->post_type_rencontre,
      'orderby'     => 'menu_order',
      'order'       => 'ASC',
      'post_status' => 'publish',
      'meta_query' => [
        'relation' => 'AND',
        [
          'key' => 'url_flux',
          'compare' => 'EXISTS',
        ],
        [
          'key' => 'url_flux',
          'compare' => '!=',
          'value' => ''
        ]
      ],
    ];

    $posts=get_posts($args);

    //$this->debug('posts with url');$this->debug($posts);
    $self=$this;
    // retire les rencontres terminées
    $posts_active=array_filter($posts,function($post_id) use ($self) {
      $statut=get_field('statut',$post_id);
      //$self->debug('statut='.print_r($statut,true));
      if(is_array($statut))
        $statut_value=$statut['value'];
      else
        $statut_value=$statut;
      return($statut_value!=='termine') ;
    });

    //$this->debug('posts_active');$this->debug($posts_active);
    return $posts_active;
  }

  /**
   * TODO : a supprimer
  **/
  function cron_add_minute( $schedules ) {
    // Adds once weekly to the existing schedules.
    $schedules[self::SCHEDULE] = array(
      'interval' => self::SCHEDULE_INTERVAL,
      'display' => esc_html__( 'Every minute' ),
    );
    return $schedules;
  }

  /**
   * TODO : a supprimer
  **/
  function on_deactivate()
  {
    $this->error(E_USER_NOTICE,'plugin desactivé','',__LINE__,$this->classname,__FILE__);

    $timestamp = wp_next_scheduled( 'rencontre_cron' );
    wp_unschedule_event( $timestamp, 'rencontre_cron' );
  }


  /**
   * appele par le cron toutes les 60 secondes self::SCHEDULE
   * TODO : a supprimer
  **/
  function cron_exec($force=false)
  {
    $this->download_json($force);
  }

  /**
   * appele en get toutes les minutes par le corn du serveur
   * -lecture des rencontres non terminées
   * -lecture flux json de chaque rencontre
   * -mise a jour du cpt rencontre
  **/
  function download_json($force=false,$echo_debug=false)
  {
    $options=$this->get_options();
    // active le debug pour l'envoyer par email
    if($options['mail_cron_run'] or $echo_debug) {
      $prev_debug=$this->debug;
      $this->debug=1;
    }
    $this->debug(__FUNCTION__);
    // lecture des flux json
    $rencontres=$this->get_rencontre();
    if(!is_array($rencontres) or !count($rencontres)) {
      $this->debug('plus de rencontres a venir ou en cours a traiter');
      if($echo_debug) {
        echo implode(PHP_EOL,$this->tdebug);
      }
      return;
    }

    if(!class_exists('ffj_rencontre_wp')) {
      require_once('class.ffj_rencontre_wp.php');
    }


    foreach($rencontres as $post_id) {
      $rencontre=new ffj_rencontre_wp($post_id,$this);

      if($options['mail_cron_run']) $rencontre->debug=1;

      $modified=$rencontre->update( $force );

      if( is_wp_error( $modified ) )
      {
        //TODO placer l'erreur dans un fichier log
      }
      else if($modified===true or $force) {
        global $ffjudo_rencontre_cache;
         $ffjudo_rencontre_cache->delete_cache($post_id);
      }
    }


    // envoi debug par email
    if($options['mail_cron_run']) {
      $dest=$options['dest_cron_run'];
      $msg=array();
      $msg[]='debug ffjudo_rencontre_cron';
      $msg=array_merge($msg,$this->tdebug);
      $this->sendmail(__FUNCTION__.' log',$msg,$dest);
      $this->debug=$prev_debug;
    }
    if($echo_debug) {
      echo implode(PHP_EOL,$this->tdebug);
    }

  }



}


global $ffjudo_rencontre_cron;
$ffjudo_rencontre_cron = new ffjudo_rencontre_cron();
register_deactivation_hook( __FILE__, [$ffjudo_rencontre_cron,'on_deactivate'] );