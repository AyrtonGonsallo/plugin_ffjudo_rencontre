<?php

class ffj_rencontre_wp {



  public $pt_rencontre = '';

  public $pt_equipe = '';

  public $post_id = 0;

  public $debug = 1;

  public $title = '';



  private $parent;



  function __construct($post_id,$parent)

  {

    $this->classname='ffj_rencontre_wp';

    $this->post_id=$post_id;

    $this->parent=$parent;

    $this->pt_rencontre=$parent->post_type_rencontre;

    $this->pt_equipe=$parent->post_type_equipe;

    $this->pt_judoka=$parent->post_type_judoka;



    $this->title=get_the_title($post_id);

    $this->debug("new ($post_id,{$this->pt_rencontre},{$this->pt_equipe},{$this->pt_judoka}) ".$this->title);

  }



  /**

   * lecture flux json de chaque rencontre

   *  et mise a jour du cpt rencontre,equipe,judoka

   * @param bool force force la mise a jour

   * @return bool true si la recontre a été modifiés, false si pas de changement

  **/

  function update( $force=false )

  {

    $this->debug(__FUNCTION__);

    $ffj_data=$this->get_json( $force );

    if( is_wp_error( $ffj_data ) ) {

      return $ffj_data;

    }

    if( $ffj_data===false ) {

      $this->debug('skip update');

      return false;

    }

    $this->debug('read json ok');



    // mise a jour des données

    $equipes = $this->update_equipes($ffj_data);



    $this->update_combats($ffj_data,$equipes);

    return true;

  }



  /**

   * lecture du flux json d'une rencontre

   * @param bool force force la mise a jour

   * @return object|wp_error ffj_rencontre_json (json décodé sous forme de tableau associatif)

  **/

  function get_json( $force=false ) {

    $this->debug(__FUNCTION__);

    $url_json=get_field('url_flux',$this->post_id);



    $this->debug('read url_json '.$url_json);

    $response=wp_remote_get($url_json);

    if( is_wp_error( $response ) ) {



      $msg=[

        'function rencontre_get_json()',

        'post_id='.$this->post_id,

        'wp_remote_get('.$url_json.') a renvoyé cette erreur ='.$response->get_error_message(),

        'verifiez si cette url est valide ou contactez la personne gerant cette url',

      ];



      return new WP_Error( $this->classname.':get_json() ', 'wp_remote_get('.$url_json.') err:'.$response->get_error_message() );

    }



    $response_code = wp_remote_retrieve_response_code($response);

    if( $response_code!=200 ) {

      $msg=[

        'function rencontre_get_json()',

        'post_id='.$this->post_id,

        'wp_remote_get('.$url_json.') a renvoyé le code http ='.$response_code,

        'verifiez si cette url est valide ou contactez la personne gerant cette url',

      ];



      return new WP_Error( $this->classname.':update_rencontre() ', 'wp_remote_get('.$url_json.') err http:'.$response_code );

    }



    $body=wp_remote_retrieve_body($response);



    $hash = md5($body);

    $this->debug('new  hash='.$hash);

    // get previous hash

    $prev_hash=$this->get_prev_jsonhash($url_json);

    $this->debug('prev hash='.$prev_hash);

    // le json na pas changé

    if( ($hash == $prev_hash) && !$force) {

      $this->debug('json no changes');

      return false;

    }

    $this->save_jsonhash($url_json,$hash);

    try {

      $data=json_decode($body,true);

    }

    catch (Exception $e) {

      $msg=[

        'function rencontre_get_json()',

        'post_id='.$this->post_id,

        'wp_remote_get('.$url_json.') a renvoyé du json non valide',

        'contenu du body=',

        htmlentities($data),

      ];



      return new WP_Error( $this->classname.':update_rencontre() ', 'json non valide:'.$e->getMessage() );

    }

    if(!class_exists('ffj_rencontre_json')) {

      require_once('class.ffj_rencontre_json.php');

    }

    $ffj_data=new ffj_rencontre_json($data,$this->parent);

    return $ffj_data;

  }



  function save_jsonhash($url_json,$hash)

  {

    $this->debug(__FUNCTION__."($url_json,$hash)");

    $dest_name=$this->get_jsonhash_file($url_json);

    if(is_wp_error($dest_name)) {

      return false;

    }

    $this->debug('dest_name='.$dest_name);

    $r=file_put_contents($dest_name,$hash,LOCK_EX); // Acquire an exclusive lock on the file while proceeding to the writing

    $this->debug('write ='.($r?'ok':'err'));

    return $r;

  }



  function get_jsonhash_file($url_json)

  {

    //$this->debug(__FUNCTION__."($url_json)");

    $upload_info = wp_upload_dir();

    $folder = 'json_rencontres_hash';

    $dest_dir = trailingslashit( $upload_info['basedir'] ).$folder;

    if ( !@is_dir( $dest_dir ) )

    {

      if(!wp_mkdir_p( $dest_dir )) {

        $msg=sprintf('Unable to create directory %s',esc_html($dest_dir) );

        return new WP_Error( 'ffjudo_rencontre_wp:get_jsonhash_file()', $msg );

      }

    }

    $file=basename($url_json);



    $dest_name=trailingslashit($dest_dir).$file;

    return $dest_name;

  }



  function get_prev_jsonhash($url_json)

  {

    //$this->debug(__FUNCTION__."($url_json)");

    $dest_name=$this->get_jsonhash_file($url_json);

    if(is_wp_error($dest_name)) return '';

    if(!file_exists($dest_name)) return '';

    $r=file_get_contents($dest_name);

    //$this->debug('prv hash='.$r);

    return $r;

  }







  /**

   * met a jour les combats la fiche rencontre

   * @param ffj_rencontre_json $ffj proxy d'acces aux données json

   * @param array $equipes [ equipe1=>[ffdid,libelle,post_id], equipe2=>[ffdid,libelle,post_id] ]

   * @return void

  **/

  function update_combats($ffj,$equipes)

  {

    $this->debug(__FUNCTION__);

    $post_id=$this->post_id;



    $ffj_combats=$ffj->get_combats($post_id);

    //$this->debug('combats');$this->debug($combats);



    $acf_combats=[];



    $scores=$ffj->get_score($post_id);

    $this->debug('scores'); $this->debug($scores);

    $nb_combat_gagne_equipe_1 = $scores['equipe1']['score'];

    $nb_combat_gagne_equipe_2 = $scores['equipe2']['score'];



    $statut_rencontre='a_venir';

    $nb_combat=count($ffj_combats);

    $nb_combat_termines=0;

    foreach($ffj_combats as $ffj_combat)

    {

    	$c=$this->get_combat_data($ffj,$ffj_combat);



    	/*if($c['gagnant']==1)     $nb_combat_gagne_equipe_1++;

    	elseif($c['gagnant']==2) $nb_combat_gagne_equipe_2++;*/



      $c['combattant_1']['equipe'] = $equipes['equipe1'];

      $c['combattant_2']['equipe'] = $equipes['equipe2'];



			$judoka_equipe_1=$this->get_judoka_id($c['combattant_1']);

			$judoka_equipe_2=$this->get_judoka_id($c['combattant_2']);



      // si au moins 1 combat a commencé la rencontre est 'en_cours'

      if($c['statut']==='en_cours') {

        $statut_rencontre='en_cours';

      }



      if($c['statut']==='termine') {

        // si au moins 1 combat terminé la rencontre est 'en_cours'

        $statut_rencontre='en_cours';

        $nb_combat_termines++;

      }

      $this->debug('statut rencontre='.$statut_rencontre);

      $this->debug('nb_combat_termines='.$nb_combat_termines);



    	$acf_combats[]=array(

				'categorie_de_poids' => $c['categorie_de_poids'],

        'duree_combat'       => apply_filters('rencontre_duree',$c['duree'],$c,$post_id),

				'temps_restant'      => apply_filters('rencontre_temps_restant',$c['temps_restant'],$c,$post_id),

        'statut'             => $c['statut'],



        'judoka_gagnant'     => $c['gagnant'],



				'judoka_equipe_1'         => $judoka_equipe_1,

				'points_judoka_1'         => $c['combattant_1']['score']['points'],
        'valeur_ippons_comptés_judoka_1'         => $c['combattant_1']['score']['ippon_comptés'],
        'valeur_ippons_comptés_judoka_2'         => $c['combattant_2']['score']['ippon_comptés'],
        'valeur_ippon_judoka_1'   => $c['combattant_1']['score']['valeur_ippon'],

				'valeur_wazari__judoka_1' => $c['combattant_1']['score']['valeur_wazari'],

				'valeurs_shidos_judoka_1' => $c['combattant_1']['score']['valeur_shidos'],



				'judoka_equipe_2'         => $judoka_equipe_2,

        'points_judoka_2'         => $c['combattant_2']['score']['points'],

				'valeur_ippon_judoka_2'   => $c['combattant_2']['score']['valeur_ippon'],

				'valeur_wazari__judoka_2' => $c['combattant_2']['score']['valeur_wazari'],

				'valeurs_shidos_judoka_2' => $c['combattant_2']['score']['valeur_shidos'],

			);



    }



    /* equipe gagnante */

    $equipe_gagnante='inconnue'; //inconnue,équipe 1,équipe 2

    if($nb_combat_gagne_equipe_1>$nb_combat_gagne_equipe_2) $equipe_gagnante='équipe 1';

    if($nb_combat_gagne_equipe_2>$nb_combat_gagne_equipe_1) $equipe_gagnante='équipe 2';



    // si egalité de combats

    if($nb_combat_gagne_equipe_2==$nb_combat_gagne_equipe_1) {

      // compare les points

      if($scores['equipe1']['points'] > $scores['equipe2']['points'] ) $equipe_gagnante='équipe 1';

      if($scores['equipe2']['points'] > $scores['equipe1']['points'] ) $equipe_gagnante='équipe 2';

    }



  	$value=array([

			"acf_fc_layout"                   => "matchs", // flexible content

			"nombre_de_combat_gagne_equipe_1" => $nb_combat_gagne_equipe_1,

      "points_equipe_1"                 => $scores['equipe1']['points'],

      "bonus_equipe_1"                  => $scores['equipe1']['bonus'],

			"nombre_de_combat_gagne_equipe_2" => $nb_combat_gagne_equipe_2,

      "points_equipe_2"                 => $scores['equipe2']['points'],

      "bonus_equipe_2"                  => $scores['equipe2']['bonus'],

			"equipe_gagnante"                 => $equipe_gagnante,

			"combats"                         => $acf_combats, // repeater

  	]);





    /* acf save : les_combat

    ajoute un match dans la liste des combats :

    -nombre_de_combat_gagne_equipe_1

    -nombre_de_combat_gagne_equipe_2

    -equipe_gagnante

    -combats (repeater) : judoka_equipe_1,valeur_ippon_judoka_1,valeur_wazari__judoka_1,valeurs_shidos_judoka_1,categorie_de_poids,judoka_gagnant

    */



    $this->acf_update_field('les_combat', $value,$post_id);
    $this->acf_update_field('mode_de_calcul_classement', "auto",$post_id);




    /* status rencontre :

    statut=

      a_venir : à venir

      en_cours : en cours : 1er combat commencé

      termine : terminé : dernier combat fini

    */



    if($nb_combat>0 && $nb_combat==$nb_combat_termines) $statut_rencontre='termine';

    $this->debug('statut rencontre='.$statut_rencontre);

    $this->debug('nb_combat='.$nb_combat);

    $this->debug('nb_combat_termines='.$nb_combat_termines);

    if($nb_combat>0) $this->acf_update_field('statut', $statut_rencontre,$post_id);

  }



  /**

   * cherche l'id judoka par son ffj_id puis nom prenom

   * @param array $args tableau associatif [ffj_id,nom,prenom,equipe=[ffdid,libelle,post_id]]

   * @return int post_id judoka

   **/

  function get_judoka_id($args) {



  	extract($args); //ffj_id,nom,prenom

  	$this->debug(__FUNCTION__."($ffj_id,$nom,$prenom)");



    // les combattant etranger remote id =null alors creation fiche judoka

    if( empty($ffj_id) ) {

      return $this->add_judoka( $args );

    }



  	$id=$this->get_judoka_from_ffid($ffj_id);

    if($id) return $id;



  	if(!empty($nom) && !empty($prenom) ) {

      $id=$this->get_judoka_from_name($nom,$prenom);

      if(!$id)

      {

        // si on a un ffid et que le judoka n'a pas été trouvé ni par son id ni par son nom+prenom alors creation fiche judoka

        $id=$this->add_judoka( $args );

      }

      if($id) {

        // ajoute l'id ffj au judoka

        $this->acf_update_field('id_ffjda',$ffj_id,$id);

      }

    }



  	return $id;

  }



   /**

   * creation d'un judoka

   * @param array $data tableau associatif ffj_id,nom,prenom,equipe=[ffdid,libelle,post_id]

   * @return int new post id or 0 on error

  **/

  function add_judoka($data)

  {

    $this->debug(__FUNCTION__);

    $this->debug('data');$this->debug($data);



    extract($data);



    if(empty($nom) or empty($prenom) )

    {

      $this->debug('error missing nom et prenom ');

      return 0;

    }



    $id=$this->get_judoka_from_name($nom,$prenom);

    if($id) return $id;





    $post_data=[

      'post_title'  => "$nom $prenom",

      'post_status' => 'publish',

      'post_type'   => $this->pt_judoka,

    ];



    if( !empty($equipe['category_id']) ) {

      $post_data['post_category']= [$equipe['category_id'] ];

    }



    if($this->debug<2)

      $new_id=wp_insert_post($post_data,false);

    else

      $new_id='test999';



    $this->debug( "wp_insert_post(".print_r($post_data,true).") = $new_id ".($this->debug==2?'deact':'') );



    if($new_id) {

      $this->acf_update_field('nom_judoka',$nom,$new_id);

      $this->acf_update_field('prenom_judoka',$prenom,$new_id);



      if( !empty($equipe['post_id']) ) {

        $this->acf_update_field('equipe_judoka',[ $equipe['post_id'] ],$new_id);

      }



      // todo :

      //$this->acf_update_field('sexe',$sexe,$new_id);

      //$this->acf_update_field('categorie_de_poids',$sexe,$new_id);

    }

    // todo equipe



    return $new_id;

  }





  function get_judoka_from_ffid($ffj_id)

  {

  	$this->debug(__FUNCTION__);

  	if(empty($ffj_id)) {

      // invalid id

      $this->debug("invalid ffj_id = $ffj_id");

      return 0;

    }

    // lecture des judoka avec un post_meta id_ffjda non vide

    $args=[

      'fields'      => 'ids',

      'numberposts' => 1,

      'post_type'   => $this->pt_judoka,

      'post_status' => 'any',

      'orderby'     => 'ID',

      'order'       => 'ASC',

      'meta_query' => [

        'relation' => 'AND',

        [

          'key' => 'id_ffjda',

          'compare' => 'EXISTS',

        ],

        [

          'key' => 'id_ffjda',

          'compare' => '=',

          'value' => $ffj_id

        ]

      ],

    ];

    //$this->debug('args');$this->debug($args);

    $posts=get_posts($args);

    if(empty($posts) or !is_array($posts)) {

      $this->debug("judoka $ffj_id not found");

      return 0;

    }



    $post_id=current($posts);

    $this->debug("judoka $ffj_id FOUND id = $post_id");



    return $post_id;

  }



  /**

   * cherche le judoka ave nom+prenom

   * @param string $nom

   * @param string $prenom

   * @return int post_id ou 0 si non trouvé

   **/

  function get_judoka_from_name($nom,$prenom)

  {

  	$this->debug(__FUNCTION__);

  	$id=0;

    $args=array('s'=>"$nom $prenom",'order'=> 'ASC', 'posts_per_page'=>1 );

    $query=new WP_Query($args);

    if( !$query->have_posts() ) {

      $this->debug("judoka $nom $prenom not found");

      return 0;

    }



    $query->the_post();

    $post_id=get_the_ID();

    wp_reset_postdata();



    $this->debug("judoka $nom $prenom FOUND id = $post_id");



    return $post_id;

  }



  function get_combat_data($ffj,$combat)

  {

  	$this->debug(__FUNCTION__);





    $combat_data=$ffj->get_combat($combat);

		/*

		TypeTrame :

		Init : début,

		Score : changement de score,

		Pause : pause,

		Continuer : continuer,

		Arret : arrêt médical,

		Fin : fin

    on ne garde que 3 :

    a venir

    en cours

    fin



    statut du combat :

    a_venir : à venir

    en_cours : en cours

    termine : terminé

    */

    switch($combat_data['type_trame'])

    {



      case 'Fin':

      $combat_data['statut']='termine';

      break;



      case 'Init':

      $combat_data['statut']='a_venir';

      break;



      case 'Score':

      case 'Pause':

      case 'Continuer':

      case 'Arret':

      default:

      $combat_data['statut']='en_cours';

      break;

    }

    $combat_data['statut']=apply_filters('rencontre_statut_combat',$combat_data['statut'],$combat,$ffj,$this->post_id);





    $score_1_detail  = $this->get_score_data($combat_data['combattant_1']['score']);

    $score_2_detail  = $this->get_score_data($combat_data['combattant_2']['score']);



		$combat_data['combattant_1']['score']=$score_1_detail;

    $combat_data['combattant_2']['score']=$score_2_detail;



    $combat_data['combattant_1']['score']['points']=$combat_data['combattant_1']['points'];

    $combat_data['combattant_2']['score']['points']=$combat_data['combattant_2']['points'];

    $combat_data['combattant_1']['score']['ippon_comptés']=$combat_data['combattant_1']['ippon_comptés'];

    $combat_data['combattant_2']['score']['ippon_comptés']=$combat_data['combattant_2']['ippon_comptés'];

    //$combat_data['combattant_1']['points']=$this->calc_points_combat($score_1_detail);

    //$combat_data['combattant_2']['points']=$this->calc_points_combat($score_2_detail);

    //$combat_data['gagnant'] = $this->calc_combat_gagnant($combat_data);

    $combat_data['gagnant'] = $combat_data['vainqueur_equipe']; // 0,1,2,N



    $combat_data['categorie_de_poids']=str_replace('kg','',$combat_data['categorie_de_poids']);



    $this->debug($combat_data);

		return $combat_data;

  }



  /**

   * calcule le nombre de points selon ippon et wasaris

   * TODO : combats individuels comment les shidos impactent le score ?

   * Système de points Pro League:

   * 20  points max par combat

   * Ippon ou 2 Waza Ari = 10 points

   * Waza Ari = 1 point

   * Hansoku Make = 20 points

   * Ex:

   * 1 Ippon + 1 Waza Ari = 11 points

   * 1 Ippon + 2 Waza Ari = 20 points

   * 3 Waza Ari = 11 points

   * @param array tableau associatif 'valeur_ippon','valeur_wazari','valeur_shidos'

   * @return int points

   **/

  function calc_points_combat($score) {

  	extract($score); // 'valeur_ippon','valeur_wazari','valeur_shidos'



  	$points = $valeur_ippon * 10;



    $double_wazari=intdiv($valeur_wazari,2);

    $reste_wazari=$valeur_wazari%2;

    $points += $double_wazari * 10;

    $points += $reste_wazari * 1;



  	$points= min(20,$points); // max 20 points

    return $points;

  }



  /**

   * fonction obsolete : le gagnant est donné dans le flux json via le champ 'vainqueur_equipe'

   * determine le judoka gagnant

   * @param array

   * @return int gagnant 1 ou 2 ou zero

   **/

  function calc_combat_gagnant($combat_data)

  {

  	if($combat_data['type_trame']!=='Fin') return 0;



    $gagnant=0;

  	// victoire judoka1 par abandon,forfait, hasoku make judoka 2

    $valeur_shidos2=$combat_data['combattant_2']['score']['valeur_shidos'];

    if(in_array($valeur_shidos2,['A','M','F','H','X']))

    {

      $gagnant=1;

    }



    // victoire judoka2 par abandon,forfait, hasoku make judoka 1

    $valeur_shidos1=$combat_data['combattant_1']['score']['valeur_shidos'];

    if(in_array($valeur_shidos1,['A','M','F','H','X']))

    {

      // penalités des 2 cotés pas de gagnants

      if($gagnant==1)

        $gagnant=0;

      else

        $gagnant=2;

    }

    // si victoire par abandon,forfait...

    if($gagnant) return $gagnant;



    // victoire aus points

    $points_1 = $combat_data['combattant_1']['points'];

    $points_2 = $combat_data['combattant_2']['points'];



  	if($points_1 > $points_2) return 1;

  	if($points_2 > $points_1) return 2;

  	return 0;

  }



  /**

   * converti le score json en score wordpress

   * @param string format 00-0

   * @return associative array 'valeur_ippon','valeur_wazari','valeur_shidos'

   **/

  function get_score_data($ffj_score)

  {

  	//$this->debug(__FUNCTION__."($ffj_score)");



  	// Pour les combats individuels :

  	// 00-0 (Le premier 0 correspond au ippon, le second au waza, et le dernier pour le shido)

  	// Pour les combats en équipe :

  	// 00 (Le premier 0 correspond au ippon, le second au waza)

    // valeur wasari :

    // 0 :

    // 1 : pénalité

    // 2 : pénalité

    // A : abandon

    // M : Arrêt médical

    // F : forfait

    // H : hansoku-make

    // X : hansoku-make direct



    $valeur_ippon  = (int)substr($ffj_score,0,1);

    $valeur_wazari = (int)substr($ffj_score,1,1);

    $valeur_shidos = substr($ffj_score,3,1); // penalité

    $valeur_shidos = strtoupper($valeur_shidos);



  	$r=compact('valeur_ippon','valeur_wazari','valeur_shidos');

  	//$this->debug($r);

		return $r;

  }



  /**

   * cherche l'id de la categorie depuis l'id d'une equipe selon le slug de l'equipe

   * @param int $equipe_id

   * @return int

  **/

  function get_equipe_cat_id($equipe_id) {



    $this->debug(__FUNCTION__.'('.$equipe_id.')');

    if($equipe_id<1) {

      $this->debug('not found equipe invalid id ='.$equipe_id);

      return 0;

    }

    $equipe_slug = get_post_field( 'post_name', $equipe_id );

    if(!$equipe_slug) {

      $this->debug('not found equipe with id='.$equipe_id);

      return 0;

    }

    $category=get_category_by_slug($equipe_slug);

    if ( !$category instanceof WP_Term ) {

      $this->debug('not found category with slug='.$equipe_slug);

      return 0;

    }

    $cat_id = $category->term_id;

    $this->debug('found equipe slug='.$equipe_slug.' cat_id='.$cat_id);

    return $cat_id;

  }



  /**

   * met a jour equipe1 et 2 de la fiche rencontre

   * @param ffj_rencontre_json $ffj proxy d'acces aux données json

   * @return array [ equipe1=>[ffdid,libelle,post_id,category_id], equipe2=>[ffdid,libelle,post_id,category_id] ]

  **/

  function update_equipes($ffj)

  {

    $this->debug(__FUNCTION__);

    $post_id=$this->post_id;



    $equipes = $ffj->get_equipes($this->post_id);

    extract($equipes);



    $result=$equipes;



    $equipe1_id=0;

    $equipe1_category_id=0;

    $equipe2_id=0;

    $equipe2_category_id=0;



    if($equipe1['libelle'])

    {

    	extract($equipe1); // ffjid,libelle



      //$equipe1_id=$this->get_equipe_from_ffid($ffjid);



      //if(!$equipe1_id) {

      	$equipe1_id=$this->get_equipe_from_name($libelle);

      	//if($equipe1_id) $this->update_equipe_fffid($equipe1_id,$ffjid);

      //}



      /*if(!$equipe1_id) {

        $equipe1_id=$this->create_equipe($equipe1);

      }*/



      //recupere category_id

      if($equipe1_id) {

        $equipe1_category_id = $this->get_equipe_cat_id($equipe1_id);

      }

    }





    if($equipe2['libelle'])

    {

    	extract($equipe2); // ffjid,libelle



      //$equipe2_id=$this->get_equipe_from_ffid($ffjid);



      //if(!$equipe2_id) {

      	$equipe2_id=$this->get_equipe_from_name($libelle);

      	//if($equipe2_id) $this->update_equipe_fffid($equipe2_id,$ffjid);

      //}



      /*if(!$equipe2_id) {

        $equipe2_id=$this->create_equipe($equipe2);

      }*/



      if($equipe2_id) {

        $equipe2_category_id = $this->get_equipe_cat_id($equipe2_id);

      }

    }





    if(!$equipe1_id )

    {

    	// ne fait rien, on garde la valeur du champ acf

    }

    else {

      // maj fiche rencontre

      $this->acf_update_field('equipe_1', [ $equipe1_id ],$this->post_id);

    }



    if(!$equipe2_id)

    {

      // ne fait rien, on garde la valeur du champ acf

      //$this->acf_delete_field('equipe_2',$this->post_id);

    }

    else {

      // maj fiche rencontre

      $this->acf_update_field('equipe_2', [ $equipe2_id ] ,$this->post_id);

    }



    $result['equipe1']['post_id']=$equipe1_id;

    $result['equipe1']['category_id']=$equipe1_category_id;

    $result['equipe2']['post_id']=$equipe2_id;

    $result['equipe2']['category_id']=$equipe2_category_id;



    return $result;

  }



  function acf_delete_field($key,$post_id) {

  	if($this->debug<2)

  		$r=delete_field($key, $post_id);

  	else $r='deact';



    if($this->debug>0) {

    	$txt_r=($r===true?'ok':($r===false?'err':$r));

    	$this->debug("delete_field($key, $post_id ) = ".$txt_r);

    }

  }



  function acf_update_field($key,$value,$post_id) {

  	if($this->debug<2)

  		$r=update_field($key, $value,$post_id);

  	else $r='deact';



    if($this->debug>0) {

    	if(is_array($value) or is_object($value))

    	{

    		$v=print_r($value,true);

    	}

    	else $v=$value;

    	$txt_r=($r===true?'ok':($r===false?'err':$r));

    	$this->debug("update_field($key,".$v.", $post_id ) = ".$txt_r);

    }

    if($this->debug==2) $r=true;

    return $r;

  }



  /**

   * creation d'une equipe

   * @param array $data Array ( [ffjid] => 36, [libelle] => JCCMM )

   * @return int new post id or 0 on error

  **/

  function create_equipe($data)

  {

    $this->debug(__FUNCTION__);

    //$this->debug('data');$this->debug($data);

    extract($data);



    $post_data=[

			'post_title'  => $libelle,

			//'post_status' => 'publish',

			'post_type'   => $this->pt_equipe,

    ];

    if($this->debug<2)

    	$new_id=wp_insert_post($post_data,false);

    else

    	$new_id='test999';



    $this->debug( "wp_insert_post(".print_r($post_data,true).") = $new_id ".($this->debug==2?'deact':'') );





    // sauve l'id ffj

    if($new_id) $this->update_equipe_fffid($new_id,$ffjid);

    return $new_id;

  }



  function update_equipe_fffid($post_id,$ffjid)

  {

  	if($post_id) $this->acf_update_field('id_ffjda',$ffjid,$post_id);

  }





  /**

   * trouve le post_id d'une equipe selon l'id ffj

  **/

  function get_equipe_from_ffid($ffj_id)

  {

    $this->debug(__FUNCTION__."($ffj_id)");



    if(empty($ffj_id)) {

      // invalid id

      $this->debug("invalid ffj_id = $ffj_id");

      return 0;

    }

    // lecture des rencontres avec un post_meta url_flux non vide

    $args=[

      'fields'      => 'ids',

      'numberposts' => 1,

      'post_type'   => $this->pt_equipe,

      'post_status' => 'any',

			'orderby'     => 'ID',

			'order'       => 'ASC',

      'meta_query' => [

        'relation' => 'AND',

        [

          'key' => 'id_ffjda',

          'compare' => 'EXISTS',

        ],

        [

          'key' => 'id_ffjda',

          'compare' => '=',

          'value' => $ffj_id

        ]

      ],

    ];

    //$this->debug('args');$this->debug($args);

    $posts=get_posts($args);

    if(empty($posts) or !is_array($posts)) {

      $this->debug("equipe $ffj_id not found");

      return 0;

    }



    $post_id=current($posts);

    $this->debug("equipe $ffj_id FOUND id = $post_id");



    return $post_id;

  }





  /**

   * recherche une equipe d'apres son nom

   * @param string nom de l'equipe

   * @return int post_id de l'equipe ou zero si non trouvée

   **/

  function get_equipe_from_name($name)

  {

    $this->debug(__FUNCTION__."($name)");



    // convert name to slug

    //$slug=sanitize_title($name);

    $name_clean=trim($name);



    if(empty($name_clean)) {

      // invalid id

      $this->debug("ERR name ($name) gives an empty clean name");

      return 0;

    }

    //$this->debug("slug=$slug");

		//$name_clean=strtolower($name);

		// recherche une equipe avec ce nom



    // ffj_nom

    $args=[

			'fields'      => 'ids',

			'numberposts' => 1,

			'post_type'   => $this->pt_equipe,

      'meta_key'    => 'ffj_nom',

      'meta_query' => [

        [

          'key' => 'ffj_nom',

          'value' => $name_clean,

          'compare' => '=',

        ]

      ],

			'post_status' => 'any',

			'orderby'     => 'ID',

			'order'       => 'ASC',

    ];

    $this->debug('args');$this->debug($args);

    $posts=get_posts($args);

    if(empty($posts) or !is_array($posts)) {

      $post_id=0;

      $this->debug("equipe $name_clean not found");

    }

    else {

      $post_id=current($posts);



      $this->debug("equipe $name_clean FOUND id = $post_id");

    }





    return $post_id;

  }



  function debug($val) {

    if(!$this->debug) return;

    $this->parent->debug('ffj_rencontre_wp:'.print_r($val,true));

  }

}