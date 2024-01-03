"use strict";
function load_rencontre()
{
  console.log('load_rencontre');
  const formData=new FormData()
  formData.set('post_id', rencontre_config.post_id)
  //formData.set('file', rencontre_config.file)
  //formData.set('subdir', rencontre_config.subdir)
  formData.set('nonce', rencontre_config.nonce)

  fetch(rencontre_config.ajaxurl, {method: 'POST', body: formData })
  .then(response => response.json() )
  .then(response => {

    console.log('response',response);
    if (!response.success) {
      console.error(response.data)
      if(rencontre_config.interval>0) {
        console.log('recall load_rencontre in ',rencontre_config.interval*2);
        setTimeout(load_rencontre,rencontre_config.interval*2);
      }
      return
    }
    const data=response.data;
    // update config
    rencontre_config.interval=data.new_interval*1000;

    // En cas d'erreur
    if (data.error!=='ok') {
      console.error('load_rencontre',data.error)
      /*
      return
      */
    }
    if(data.user_data && data.user_data.html.length) {

      const html=decodeURIComponent(data.user_data.html)
      console.log('update rencontre html',rencontre_config.selector,'length',html.length);
      const target=document.querySelector(rencontre_config.selector)
      target.outerHTML=html
      target.setAttribute('data-updated',(new Date()).toLocaleString())

      if(rencontre_config.cb_after_update.length && typeof window[rencontre_config.cb_after_update] === 'function') {
        try {
          window[rencontre_config.cb_after_update](rencontre_config.selector)
        }
        catch (error) {
          console.error(error);
        }
      }

    } else  console.log('no update rencontre html empty');

    if(rencontre_config.interval>0) {
      console.log('recall load_rencontre in ',rencontre_config.interval);
      setTimeout(load_rencontre,rencontre_config.interval);
    }
    else {
      console.log('recall load_rencontre : stopped');
    }
  });

}

try {
  console.log("rencontre_config",rencontre_config)
  if(typeof rencontre_config ==='object') {
    setTimeout(load_rencontre,rencontre_config.interval);
  }
} catch (error) {
  console.error(error);
}