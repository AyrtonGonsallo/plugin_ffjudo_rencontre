class.ffjudo_rencontre_cron ligne 177 appel download_json
class.ffjudo_rencontre_cron ligne 186 code download_json
class.ffjudo_rencontre_cron ligne 103 recupere les posts type rencontre avec flux
class.ffjudo_rencontre_cron ligne 210 boucle
class.ffjudo_rencontre_cron ligne 215 $rencontre->update( $force ); lit les flux et mets a jour les rencontres

class.ffj_rencontre_wp ligne 61 function update( $force=false )
class.ffj_rencontre_wp ligne 67 recupere les donnees du json
class.ffj_rencontre_wp ligne 83 (changement a partir de là)
class.ffj_rencontre_wp ligne 89 appel update equipes
class.ffj_rencontre_wp ligne 1296 code update equipes
class.ffj_rencontre_wp ligne 1306 recupere les equipes 

toutes les modifs sont faites dans class.ffj_rencontre_json sur les fonctions get_combats($post_id=2) et get_equipes($post_id=1) et
dans class.ffj_rencontre_wp avec le paramatre post id pour l'appel de ces fonctions.
creer le judoka si il n'existe pas 
vers la ligne 622 dans class.ffj_rencontre_wp

nouveau changement rencontreID devient id
ligne 488 class.ffj_rencontre_wp en cas d'egalité determiner le gagnant dans le dernier combat

//if($nb_combat_gagne_equipe_2==$nb_combat_gagne_equipe_1) {
pour trouver les erreurs taper http://www.rimo0631.odns.fr/?rencontre_dl_json=force dans la nvigateur et regarder soit 
le debug display soit wp-content/debug.txt


recuperer des judokas fantomes si vide nom1 ou prenom1 = "-" 
la fonction est ici qui cree les judokas etait ici
class.ffj_rencontre_wp 608 //les combattant etranger remote id =null alors creation fiche judoka
ils l'appellent la
class.ffj_rencontre_wp 339 401 $judoka_equipe_1=$this->get_judoka_id($c['combattant_1']);
l'argument est cree ici class.ffj_rencontre_json.php 262 278  'combattant_1'=>[


mon code est la
class.ffj_rencontre_wp 604 verifier si c'est un judoka non presenté


pour que ca marche 
les equipes doivent avoir le champ ffj_nom egal au champ Equipe2 ou Equipe1 de rencontres dans le json de la federation
il faut créer des judokas masqués avec nom="nom judoka [id_equipe]" et prenom="prenom judoka [id_equipe]"