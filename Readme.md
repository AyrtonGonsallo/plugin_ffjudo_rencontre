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