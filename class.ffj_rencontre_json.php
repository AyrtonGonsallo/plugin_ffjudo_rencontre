<?php



/**

 * permet d'acceder aux données du tableau associatif decode depuis le json ffj

 * proxy d'acces aux données json

**/

/*

v1.02 20/09/2023 : lecture judoka1 points et judoka2 points

v1.01 12/09/2023 : n'utilise plus le ffid des equipes car il change selon les rencontres

*/



class ffj_rencontre_json {



  public $data=[];

  public $debug=1;

  private $parent;

  function __construct($data,$parent)

  {

    $this->data=$data;

    $this->parent=$parent;

    $this->debug('new ffj_rencontre');

    //$this->debug($data);

  }



  /**

   * extrait les id ffj du json

   * @return array equipe1_ffjid equipe2_ffjid

  **/



  function get_equipes($post_id=1)

  {

    $this->debug(__FUNCTION__);



    $result=[

      'equipe1'=>[

        'libelle'=>'',

      ],

      'equipe2'=>[

        'libelle'=>'',

      ],

    ];



    //$combats=$this->get_combats();

    /*if(empty($combats) ) {

      $this->debug('ERR no combats !');

      return $result;

    }*/

    //$this->debug('nb combats ='.count($combats));



    //$combat=current($combats);

    //$this->debug('combat[0]'); $this->debug($combat);



    //$id_1=$combat['Trame']['Equipe1RemoteId'];

    //$id_2=$combat['Trame']['Equipe2RemoteId'];

    //$libelle_1=$combat['Trame']['Equipe1Libellle'];

    //$libelle_2=$combat['Trame']['Equipe2Libelle'];
    $rencontres=$this->data['Rencontres'];
    $plusieurs_rencontres=(count($rencontres)>1)?true:false;
    if($plusieurs_rencontres){
      $this->debug('plusieurs rencontres found'); $this->debug(count($rencontres));
      foreach ($rencontres as $rencontre) {
        if($rencontre['Id']==get_field( "id_rencontre_flux", $post_id )){
          $this->debug('match avec la rencontre n°'.$post_id." qui a le flux d'url ".get_field( "url_flux", $post_id )." er l'id rencontre flux ".get_field( "id_rencontre_flux", $post_id ));
          if(!empty($rencontre['Equipe1'])) $result['equipe1']['libelle'] = $rencontre['Equipe1'];

          if(!empty($rencontre['Equipe2'])) $result['equipe2']['libelle'] = $rencontre['Equipe2'];
        }
      }
    }else{
      if(!empty($rencontres[0]['Equipe1'])) $result['equipe1']['libelle'] = $rencontres[0]['Equipe1'];

      if(!empty($rencontres[0]['Equipe2'])) $result['equipe2']['libelle'] = $rencontres[0]['Equipe2'];
    }

    





    $this->debug('equipes found'); $this->debug($result);



    return $result;

  }




  


  function get_combats($post_id=2)

  {

    $this->debug(__FUNCTION__);

    $rencontres=$this->data['Rencontres'];
    $plusieurs_rencontres=(count($rencontres)>1)?true:false;
    if($plusieurs_rencontres){
      $this->debug('plusieurs rencontres found'); $this->debug(count($rencontres));
      foreach ($rencontres as $rencontre) {
        if($rencontre['Id']==get_field( "id_rencontre_flux", $post_id )){
          $this->debug('match avec la rencontre n°'.$post_id." qui a le flux d'url ".get_field( "url_flux", $post_id )." er l'id rencontre flux ".get_field( "id_rencontre_flux", $post_id ));
          $combats=$rencontre['Combats'];

          if(!is_array($combats)) $combats=[];



          if(empty($combats) )

            $this->debug('ERR no combats !');

          else

            $this->debug('nb combats ='.count($combats));
        }
      }
    }else{

      $this->debug('une seule rencontre found'); 
      $this->debug(count($rencontres));

      $combats=$rencontres[0]['Combats'];

      if(!is_array($combats)) $combats=[];
  
  
  
      if(empty($combats) )
  
        $this->debug('ERR no combats !');
  
      else
  
        $this->debug('nb combats ='.count($combats));
    }

    return $combats;

  }



  function get_combat($combat)

  {

    $categorie_de_poids = $combat['Categorie'];

    $type_trame         = $combat['Trame']['TypeTrame'];

    $duree              = $combat['Temps'];

    $temps_restant      = $combat['Trame']['TempsRestant'];

    $EstDecisif = $combat['EstDecisif'];

    $score_1  = $combat['Trame']['Score1'];

    $points_1 = $combat['Trame']['Points1'];

    $nom_1    = $combat['Trame']['Nom1'];

    $prenom_1 = $combat['Trame']['Prenom1'];

    $ffj_id_1 = $combat['Trame']['Combattant1RemoteId'];



    $score_2  = $combat['Trame']['Score2'];

    $points_2 = $combat['Trame']['Points2'];

    $nom_2    = $combat['Trame']['Nom2'];

    $prenom_2 = $combat['Trame']['Prenom2'];

    $ffj_id_2 = $combat['Trame']['Combattant2RemoteId'];
    $ippon_comptés_1   = $combat['Trame']['Ippon1'];
    $ippon_comptés_2   = $combat['Trame']['Ippon2'];



    $vainqueur_equipe = $combat['Trame']['VainqueurEquipe'];



    return [

      'categorie_de_poids' => $categorie_de_poids,

      'EstDecisif' => $EstDecisif,

      'duree'              => $duree,

      'temps_restant'      => $temps_restant,

      'type_trame'         => $type_trame,

      'vainqueur_equipe'   => $vainqueur_equipe,

      'combattant_1'=>[

        'score'  => $score_1,

        'points' => $points_1,

        'nom'    => $nom_1,

        'prenom' => $prenom_1,

        'ffj_id' => $ffj_id_1,
        
        'ippon_comptés' => $ippon_comptés_1,

      ],

      'combattant_2'=>[

        'score'  => $score_2,

        'points' => $points_2,

        'nom'    => $nom_2,

        'prenom' => $prenom_2,

        'ffj_id' => $ffj_id_2,

        'ippon_comptés' => $ippon_comptés_2,

      ],

    ];

  }



  function get_score($post_id = 2)

  {

    $this->debug(__FUNCTION__);

    $s1=[0,0];

    $s2=[0,0];

    $rencontres=$this->data['Rencontres'];
    $plusieurs_rencontres=$rencontres?true:false;
    if($plusieurs_rencontres){
      $this->debug('plusieurs rencontres found'); $this->debug(count($rencontres));
      foreach ($rencontres as $rencontre) {
        if($rencontre['Id']==get_field( "id_rencontre_flux", $post_id )){
            $this->debug('match avec la rencontre n°'.$post_id." qui a le flux d'url ".get_field( "url_flux", $post_id )." er l'id rencontre flux ".get_field( "id_rencontre_flux", $post_id ));
            
            if(!empty($rencontre['ScoreEquipe1'])) $s1=explode('v.',$rencontre['ScoreEquipe1']);

            if(!empty($rencontre['ScoreEquipe2'])) $s2=explode('v.',$rencontre['ScoreEquipe2']);



            $b1=$rencontre['BonusIpponEquipe1'] ?? 0;

            $b2=$rencontre['BonusIpponEquipe2'] ?? 0;



            $result=[

              'equipe1'=>[

                'score'  => $s1[0],

                'points' =>(!empty($s1[1])?$s1[1]:0),

                'bonus'  => $b1,

              ],

              'equipe2'=>[

                'score'  => $s2[0],

                'points' =>(!empty($s2[1])?$s2[1]:0),

                'bonus'  => $b2,

              ],

            ];
        }
      }
    }else{
      
          if(!empty($this->data['ScoreEquipe1'])) $s1=explode('v.',$this->data['ScoreEquipe1']);

          if(!empty($this->data['ScoreEquipe2'])) $s2=explode('v.',$this->data['ScoreEquipe2']);



          $b1=$this->data['BonusIpponEquipe1'] ?? 0;

          $b2=$this->data['BonusIpponEquipe2'] ?? 0;



          $result=[

            'equipe1'=>[

              'score'  => $s1[0],

              'points' =>(!empty($s1[1])?$s1[1]:0),

              'bonus'  => $b1,

            ],

            'equipe2'=>[

              'score'  => $s2[0],

              'points' =>(!empty($s2[1])?$s2[1]:0),

              'bonus'  => $b2,

            ],

          ];
    }

    return $result;

  }



  function debug($val)

  {

    if(!$this->debug) return;

    $this->parent->debug('ffj_rencontre_json:'.print_r($val,true));

  }

}