<?php

/*

Plugin Name: ffjudo rencontre

Description: Récupère les résultats du flux json d'une rencontre et met en cache l'affichage des résultats

Version: 1.10

Author: Coudre Olivier

*/



/*
1.09 03/01/2024 : (feat) prise en charge des rencontres multiples dans un fichier
1.08 02/10/2023 : (feat) met a jour le champ mode de calcul du classement dans les rencontres pour que dans le code on ne fasse plus d'analyse et qu'on lise juste la valeur ippons_comptés
1.07 28/09/2023 : (feat) lecture et sauvegarde des ippons comptés par le commissaire(ippon1, ippon2 dans le fichier json) 
1.06 22/09/2023 : (fix) corrigé bug seule l'equipe1 pouvait ganger aux points

1.05 20/09/2023 : (fix) equipe gagnante compare les points si égalité nb combats

1.05 20/09/2023 : (feat) lecture et sauvegarde bonus equipe1 equipe2

1.04 13/09/2023 : (up) affiche le champ ffj_nom dans la liste des equipe

                  (up) recherche une equipe uniquement via le champ ffj_nom

1.03 12/09/2023 : (up) equipe ignore le ffjid la recherche se fait sur le nom uniquement

                  (fix) get_json correction fatal error si wp_remote_get renvoyait une erreur

                  (fix) ne sauve pas le statut si le nb de combats est a zero , permet de gerer un bug du fichier json ou les combats sont vides mais la rencontre est bien en cours

                  (fix) sauvait le statut comme termine au lieu de a venir si le nb de combats etait a zero

                  (fix) ne sauve pas l'equipe dans la fiche rencontre si son id est absent du json

1.02 12/09/2023 : (feat) ajoute un callback after_update en js,

                  (feat) ajout filter 'rencontre_js_args'

                  (feat) ne charge pas le js si la rencontre est terminée

1.01 11/09/2023 : (feat) efface le cache si modif fiche rencontre

                   creation judoka : ajoute equipe

                   sauve score equipe

                   sauve points equipe

                   sauve status combat

                   sauve status rencontre

1.0 11/08/2023 création

*/

if ( ! defined( 'ABSPATH' ) ) {

  exit;

}



if(!class_exists('plugin_base')) require_once  WP_PLUGIN_DIR.'/site-framework/class.plugin_base.php';



/**

 * mise en cache de l'affichage des resultats

 * rechargement automatique du tableau des resultats toutes les x secondes

**/

class ffjudo_rencontre_cache extends plugin_base{



  public $option_name='ffjudo_rencontre_cache';

  public $post_type_rencontre = 'rencontre';

  public $debug=1;



  function __construct()

  {

    $domain = parse_url(site_url(),PHP_URL_HOST);

    $this->def_options=array(

      'debug' => array (

        'active'    => $this->debug,

        'mail_dest' => 'zetoun.17@gmail.com',

        'mail_from' => 'ffjudo_rencontre_cache <noreply@'.$domain.'>',

      ),

      'cache_folder'=>'resultats_rencontres', // store html in wp-contents/uploads/resultats_rencontres

    );



    $this->init(__FILE__); // init debug,plugin_dir_path, options

    $this->classname='ffjudo_rencontre';

    $this->transient_name='ffjudo_rencontre'; // utilisée par debug_late_save()



    if(is_admin())

    {

      // efface le cache quand on modifie une fiche rencontre depuis le backoffice

      add_action('save_post_'.$this->post_type_rencontre,[$this,'on_save_rencontre'],10,3);

    }

    else

    {

      add_shortcode('fiche_rencontre', [$this,'shortcode_fiche_rencontre']);

      add_action( 'wp_enqueue_scripts', [$this,'register_js'] );

      add_filter( 'rencontre_add_inline_js', [$this,'filter_inline_js'],10,3 );



    }

    add_filter( 'rencontre_get_cache', [$this,'filter_read_cache'],10,2 );

    add_filter( 'rencontre_set_cache', [$this,'filter_write_cache'],10,2 );

    add_filter( 'rencontre_cache_filename', [$this,'filter_cache_filename'],10,2 );

    $this->init_ajax();

  }



  // efface le cache quand on modifie une fiche rencontre depuis le backoffice

  public function on_save_rencontre( $post_id, $post, $update )

  {

    $this->delete_cache($post_id);

  }



  public function filter_cache_filename($file,$post_id)

  {

    return $post_id.'.json';

  }



  function init_ajax()

  {

    add_action('wp_ajax_rencontre_reload', [$this,'trait_ajax_data']);

    add_action('wp_ajax_nopriv_rencontre_reload', [$this,'trait_ajax_data']);

  }



  function trait_ajax_data()

  {

    $this->debug('trait_post_data');



    if(empty($_POST)) {

      return;

    }



    // securite

    if(

     !isset( $_REQUEST['nonce'] ) or

     !wp_verify_nonce( $_REQUEST['nonce'], 'rencontre_reload' )

    ) {

      if( wp_doing_ajax() ) {

        wp_send_json_error( "Vous n'avez pas l'autorisation d'effectuer cette action.", 403 );

        exit;

      }



      wp_safe_redirect( '/' );

      exit;

    }



    // input sanitization

    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);



    $result=[

      'new_interval' =>30,

      'user_data'    =>null,

      'error'        =>'ok',

    ];



    if($post_id<1)  {

      wp_send_json_error( "missing post_id", 403 );

      die;

    }



    $file   = apply_filters('rencontre_cache_filename','',$post_id);

    $subdir = apply_filters('rencontre_cache_dir',date('Y'),$post_id);



    $cache_args=[

      'file'   => $file,

      'subdir' => $subdir,

    ];



    $data=$this->filter_read_cache('',$cache_args);

    if(is_wp_error($data)) {

      $result['error']=$data->get_error_message();

      wp_send_json_success($result);

      exit;

    }

    else if(empty($data) or empty($data['html']) ) {

      $this->debug('cache is empty');

      // generate content ?

      // oui on risque de lancer plusieurs maj simultanée de la rencontre

      // non attend que le cache soit genéré

      $data=apply_filters('rencontre_generate_content',['html'=>'','statut'=>''],$post_id);

      $data['html']=rawurlencode($data['html']); // permet de stocker du html en json

      $dest_name=$this->filter_write_cache(json_encode($data),$cache_args);

      if(is_wp_error($dest_name)) {

        $result['error']=$dest_name->get_error_message();

        wp_send_json_success($result);

        exit;

      }

      $data['filemtime']=date('Y-m-d H:i:s');

      $result['debug']='generated content';

    }

    else

    {

      $result['debug']='cached content';

    }





    $result['user_data']=$data;

    $result=apply_filters('rencontre_cache_interval',$result);



    if ( wp_doing_ajax() ) {

      wp_send_json_success($result);

      exit;

    }

    //wp_send_json_success($result);

    exit;

  }



  /**

   * recupere le html mis en cache

   * @param array $args required file Optional subdir, optional json bool decode data if true

   * @return string|array|wp_error html or error

  */

  public function filter_read_cache($result,$args) {

    $defaults=[

      'file'   => '',

      'subdir' => '',

      'json'   => true,

    ];

    extract(wp_parse_args($args,$defaults));

    $this->debug("read_cache(file=$file, subdir=$subdir)");

    if(empty($file)) {

      return new WP_Error( 'ffjudo_rencontre:read_cache()', __( 'missing filename' ) );

    }



    $dest_name=$this->get_cache_filename($file,$subdir);

    $this->debug("dest_name=$dest_name");

    if(is_wp_error($dest_name)) return $result;

    if(!file_exists($dest_name)) return $result;



    $result=file_get_contents($dest_name);

    if(!$json) return $result;



    try {

      $data=json_decode($result,true);

      // ajoute la date de modif du fichier, surveille si la mise en jour par le cron fonctionne bien

      $m=filemtime($dest_name);

      $data['filemtime']=date('Y-m-d H:i:s',$m);

    }

    catch (Exception $e) {

      return new WP_Error( 'ffjudo_rencontre:read_cache() ', 'json_decode:'.$e->getMessage() );

    }



    return $data;

  }



  /**

   * genere le nom du repertoire contenant le cache et crée le dossier s'il n'existe pas

   * @param string $subdir optionel nom du sous repertoire

   * @return string full folder path without trailing slash

  **/

  public function get_cache_dir($subdir='')

  {

    $upload_info = wp_upload_dir();

    if(empty($upload_info['basedir'])) {

      return new WP_Error( 'ffjudo_rencontre:get_cache_dir()', __( 'empty wp upload base dir' ) );

    }



    $options = $this->get_options();

    $folder = trailingslashit($options['cache_folder']).$subdir;



    $dest_dir = trailingslashit( $upload_info['basedir'] ).$folder;

    if ( !@is_dir( $dest_dir ) )

    {

      if(!wp_mkdir_p( $dest_dir )) {

        $msg=sprintf('Unable to create directory %s',esc_html($dest_dir) );

        return new WP_Error( 'ffjudo_rencontre:get_cache_dir()', $msg );

      }

    }

    // permet de changer le dossier depuis le theme

    //$dest_dir=apply_filters('ffjr_cache_folder',$dest_dir,$subdir);



    return $dest_dir;

  }



  /**

   * genere le nom du fichier contenant le cache

   * @param string $file nom du fichier

   * @param string $subdir optionel nom du sous repertoire

   * @return string full folder path without trailing slash

  **/

  public function get_cache_filename($file,$subdir='')

  {

    if(empty($file)) {

      return new WP_Error( 'ffjudo_rencontre:get_cache_filename()', __( "filename is empty", 'site' ) );

    }

    $dest_dir=$this->get_cache_dir($subdir);

    if(is_wp_error($dest_dir)) {

      return $dest_dir;

    }

    return trailingslashit($dest_dir).$file;

  }



  /**

   * sauve dans un fichier le html des résultats d'une rencontre

   * @param array $args required file Optional subdir

   * @return string|wp_error filename or error

  **/

  public function filter_write_cache($html,$args)

  {

    $defaults=[

      'file'   =>'',

      'subdir' =>'',

    ];

    extract(wp_parse_args($args,$defaults));

    $this->debug("write_cache(file=$file, subdir=$subdir)");

    if(empty($html)) {

      return new WP_Error( 'ffjudo_rencontre:write_cache()', __( 'html is empty nothing to save' ) );

    }

    if(empty($file)) {

      return new WP_Error( 'ffjudo_rencontre:write_cache()', __( 'missing filename' ) );

    }



    $dest_name=$this->get_cache_filename($file,$subdir);

    if(is_wp_error($dest_name)) {

      return $dest_name;

    }

    $this->debug('dest_name='.$dest_name);

    $r=file_put_contents($dest_name,$html,LOCK_EX); // Acquire an exclusive lock on the file while proceeding to the writing

    $this->debug('write ='.($r?'ok':'err'));

    return $dest_name;

  }



  public function delete_cache($post_id)

  {

    $this->debug(__FUNCTION__."($post_id)");

    $post_id=(int)$post_id;

    if($post_id<1) return;



    $file  = apply_filters('rencontre_cache_filename','',$post_id);

    $subdir = apply_filters('rencontre_cache_dir',date('Y'),$post_id);



    $dest_name=$this->get_cache_filename($file,$subdir);

    $this->debug("file=$file, subdir=$subdir");

    if(is_wp_error($dest_name)) {

      return $dest_name;

    }

    $this->debug("delete dest_name=$dest_name");

    if(file_exists($dest_name)) unlink($dest_name);

  }



  public function register_js()

  {

    wp_register_script( 'reload_rencontre', $this->plugin_url.'reload_rencontre.js', [], '1.0' );

  }



  public function add_js()

  {

    $this->debug(__FUNCTION__);

    wp_enqueue_script( 'reload_rencontre');

  }



  /**

   * javascript contenant la configuration pour le rechargement

   * @param string $js

   * @param array $args string $selector, int $post_id post_id de la rencontre, int $interval interval in seconds

   * @param array $data array[html=>string,status=>array[labe,value]]

   * @return string html javasript

  **/

  public function filter_inline_js($js,$args,$data)

  {

    $this->debug(__FUNCTION__);



    $defaults=[

      'selector'        => '.nv-bg-rencontre', // selection de l'element html a mettre a jour

      'post_id'         => null,               // post_id rencontre

      'interval'        => 10,                 // delai en secondes de rechargement du tableau

      'cb_after_update' => '',                 // fonction appelée après chaque fetch

    ];

    $args=wp_parse_args($args,$defaults);



    $args=apply_filters('rencontre_js_config',$args);



    $args['ajaxurl']   = admin_url( 'admin-ajax.php' ).'?action=rencontre_reload';

    $args['interval'] *= 1000; // convert delay from sec to ms

    $args['nonce']     = wp_create_nonce('rencontre_reload');



    $js='

    <script data-generator="ffjudo_rencontre/filter_inline_js">

    var rencontre_config='.json_encode($args).'

    </script>

    ';

    return $js;

  }



  /**

   * affiche le tableau des résultats d'une rencontre

   * usage : [fiche_rencontre]

   * @param array $atts

   *  post_id int required,

   *  use_cache bool,

   *  file string required,

   *  subdir string optional

   * @return string html

  */

  function shortcode_fiche_rencontre($atts)

  {

    extract(shortcode_atts(array(

      'post_id'   => get_the_ID(), // post_id de la rencontre

      'use_cache' => 1,

    ), $atts));



    $this->debug('shortcode_fiche_rencontre() use cache='.$use_cache);



    $file  = apply_filters('rencontre_cache_filename','',$post_id);

    $subdir = apply_filters('rencontre_cache_dir',date('Y'),$post_id);



    // recupere le contenu du cache

    $cache_args=[

    'file'   => $file,

    'subdir' => $subdir,

    ];



    $js='';





    if($use_cache

        && empty($_GET['rencontre_no_cache'])  // permet de forcer la mise à jour du cache ?rencontre_no_cache=1

      ) {



      $data=$this->filter_read_cache('',$cache_args);



      if(is_wp_error($data)) {

        $this->debug('read cache error : '.$data->get_error_message());

      }

      else if(!empty($data['html'])) {

        $ts=strtotime($data['filemtime']);

        $age=time() - $ts;

        $this->debug('use cache date='.$data['filemtime'].' age='.$age.' sec');

        $html=$data['html'];

        $html=rawurldecode($html);



        $js_args=[

          'selector' => '.nv-bg-rencontre',

          'interval' => 10,

          'post_id'   => $post_id,

        ];

        $js=apply_filters('rencontre_add_inline_js','',$js_args,$data);

        if(!empty($js)) $this->add_js();



        return $html.$js;

      }

      else {

        $this->debug('cache is empty');

      }

    }



    // si pas de cache alors genere le contenu



    $data=apply_filters('rencontre_generate_content',['html'=>'','statut'=>''],$post_id);

    $html=$data['html'];



    if($use_cache) {



      $data['html']=rawurlencode($html); // permet de stocker du html en json

      //sauve en cache le html sans le js

      apply_filters('rencontre_set_cache',json_encode($data),$cache_args);



      $js_args=[

          'selector' => '.nv-bg-rencontre',

          'interval' => 10,

          'post_id'   => $post_id,

        ];

      $js=apply_filters('rencontre_add_inline_js','',$js_args,$data);

      if(!empty($js)) $this->add_js();

    }



    return $html.$js;

  }

}



require_once('class.ffjudo_rencontre_cron.php');



global $ffjudo_rencontre_cache;

$ffjudo_rencontre_cache = new ffjudo_rencontre_cache();

