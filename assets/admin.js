document.addEventListener('DOMContentLoaded',()=>{
 const root=document.getElementById('pov-admin-groups');if(!root)return;
 const box=document.querySelector('#pov_options .inside');
 if(box){
  const intro=document.createElement('div');intro.className='pov-studio-intro';intro.innerHTML='<span class="pov-studio-icon">▦</span><div><h2>Print Options Studio</h2><p>Configurează prețuri, cantități, grafică și opțiuni vizuale pentru produs.</p></div>';box.prepend(intro);
  const tabs=document.createElement('nav');tabs.className='pov-tabs';tabs.setAttribute('aria-label','Secțiuni configurator');
  const settings=box.querySelector('.pov-admin-settings');
  const all=[...box.children];const groupStart=all.findIndex(x=>x===root);const settingsStart=all.indexOf(settings);
  const pane1=document.createElement('section');pane1.className='pov-pane';pane1.dataset.pane='pricing';
  const pane2=document.createElement('section');pane2.className='pov-pane';pane2.dataset.pane='artwork';pane2.hidden=true;
  const paneQuote=document.createElement('section');paneQuote.className='pov-pane';paneQuote.dataset.pane='quote';paneQuote.hidden=true;
  const pane3=document.createElement('section');pane3.className='pov-pane';pane3.dataset.pane='groups';pane3.hidden=true;
  const pane4=document.createElement('section');pane4.className='pov-pane';pane4.dataset.pane='bulk';pane4.hidden=true;
  if(settings){
   const nodes=[...settings.children];let target=pane1;
   nodes.forEach(node=>{
    if(node.tagName==='H3'){
     if(node.textContent.includes('Cerere ofertă'))target=paneQuote;
     else if(node.textContent.includes('Grafică'))target=pane2;
     else target=pane1;
    }
    target.appendChild(node);
   });
   settings.replaceWith(pane1);pane1.after(pane2);pane2.after(paneQuote);
  }
  pane3.innerHTML='<h3>Grupuri și opțiuni</h3><p class="description">Fiecare grup conține cardurile afișate clientului. Modificările se salvează odată cu produsul.</p>';
  const add=document.getElementById('pov-add-group');pane3.append(root);if(add)pane3.append(add);
  paneQuote.after(pane3);pane3.after(pane4);
  pane4.innerHTML='<h3>Modificări în masă și export</h3><div class="pov-csv-help"><strong>Export / import WooCommerce CSV</strong><br>Produse → Toate produsele → Exportă. Include coloanele <code>Print Options - Settings (JSON)</code> și <code>Print Options - Groups (JSON)</code>.<br>Editează valorile în CSV, păstrând JSON valid, apoi folosește Produse → Importă și identifică produsele după ID sau SKU.<br><strong>Atenție:</strong> importul înlocuiește configurația completă din coloana respectivă. Fă backup și testează întâi pe 1–2 produse.</div>';
  const config=[['pricing','Prețuri și cantități'],['artwork','Grafică și template-uri'],['quote','Cerere ofertă'],['groups','Grupuri de opțiuni'],['bulk','Import / export']];
  config.forEach(([id,label],i)=>{const b=document.createElement('button');b.type='button';b.className='pov-tab';b.textContent=label;b.dataset.tab=id;b.setAttribute('aria-selected',i===0?'true':'false');b.addEventListener('click',()=>{tabs.querySelectorAll('button').forEach(t=>t.setAttribute('aria-selected',String(t===b)));[pane1,pane2,paneQuote,pane3,pane4].forEach(p=>p.hidden=p.dataset.pane!==id)});tabs.append(b)});
  intro.after(tabs);
  // Remove only obsolete separators and headings left behind by original markup.
  [...box.children].forEach(n=>{if([intro,tabs,pane1,pane2,paneQuote,pane3,pane4].includes(n)||n.matches('script, input[type=hidden]'))return;if(n.tagName==='HR'||n.tagName==='H3'||(n.tagName==='P'&&n.textContent.includes('Opțiunile sunt afișate')))n.remove()});
 }
 const group=()=>document.getElementById('pov-group-template').innerHTML;
 const option=()=>document.getElementById('pov-option-template').innerHTML;
 function decorate(){root.querySelectorAll('.pov-admin-group').forEach(g=>{
  if(!g.querySelector('.pov-group-body')){const body=document.createElement('div');body.className='pov-group-body';[...g.children].filter(n=>!n.classList.contains('pov-admin-head')).forEach(n=>body.append(n));g.append(body);const head=g.querySelector('.pov-admin-head');const tools=document.createElement('span');tools.className='pov-group-tools';const toggle=document.createElement('button');toggle.type='button';toggle.className='pov-collapse';toggle.textContent='Restrânge';tools.append(toggle,head.querySelector('.pov-remove-group'));head.append(tools);toggle.addEventListener('click',()=>{g.classList.toggle('is-collapsed');toggle.textContent=g.classList.contains('is-collapsed')?'Extinde':'Restrânge'});}
  const count=g.querySelectorAll('.pov-admin-option').length;let badge=g.querySelector('.pov-count');if(!badge){badge=document.createElement('span');badge.className='pov-count';g.querySelector('.pov-admin-head strong').after(badge)}badge.textContent=` · ${count} opțiuni`;
  g.querySelectorAll('.pov-admin-option').forEach(o=>{if(!o.querySelector('.pov-thumb')){const img=document.createElement('img');img.className='pov-thumb';img.alt='Previzualizare';o.prepend(img)}const img=o.querySelector('.pov-thumb'),input=o.querySelector('.pov-image');img.src=input.value||'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="52" height="48"%3E%3Ctext x="26" y="29" text-anchor="middle" fill="%238397aa" font-size="20"%3E%2B%3C/text%3E%3C/svg%3E';});
 });}
 decorate();
 document.getElementById('pov-add-group').addEventListener('click',()=>{root.insertAdjacentHTML('beforeend',group());decorate()});
 root.addEventListener('input',e=>{if(e.target.matches('.pov-image'))decorate()});
 root.addEventListener('click',e=>{
  const add=e.target.closest('.pov-add-option'),del=e.target.closest('.pov-remove-option'),remove=e.target.closest('.pov-remove-group'),media=e.target.closest('.pov-media');
  if(add){add.closest('.pov-admin-group').querySelector('.pov-admin-options').insertAdjacentHTML('beforeend',option());decorate()}
  if(del){del.closest('.pov-admin-option').remove();decorate()}
  if(remove&&window.confirm('Ștergi acest grup și toate opțiunile sale?')){remove.closest('.pov-admin-group').remove()}
  if(media){const input=media.closest('.pov-admin-option').querySelector('.pov-image');const frame=wp.media({title:'Alege imaginea',multiple:false,library:{type:'image'}});frame.on('select',()=>{input.value=frame.state().get('selection').first().toJSON().url;decorate()});frame.open()}
 });
 const form=root.closest('form');if(form)form.addEventListener('submit',()=>{
  const groups=[...root.querySelectorAll('.pov-admin-group')].map(g=>({title:g.querySelector('.pov-group-title').value,required:g.querySelector('.pov-required').checked,multiple:g.querySelector('.pov-multiple').checked,options:[...g.querySelectorAll('.pov-admin-option')].map(o=>({label:o.querySelector('.pov-label').value,price:o.querySelector('.pov-price').value,charge:o.querySelector('.pov-charge').value,image:o.querySelector('.pov-image').value}))}));
  let field=form.querySelector('[name=pov_json]');if(!field){field=document.createElement('input');field.type='hidden';field.name='pov_json';form.appendChild(field)}field.value=JSON.stringify(groups);
 });
});
