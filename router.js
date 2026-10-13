document.addEventListener("DOMContentLoaded", () => {
  const links = document.querySelectorAll(".sidebar .nav-link.ajax-link");
  const main = document.getElementById("main-content");

  function load(url, push=true){
    fetch(url).then(r=>r.text()).then(html=>{
      main.innerHTML = html;
      // Setelah konten dimuat ulang, panggil ulang binding event lagu
      if (window.bindSongEvents) window.bindSongEvents();
      if(push) history.pushState({url}, "", url);
    });
  }

  links.forEach(l=>{
    l.addEventListener("click", e=>{
      e.preventDefault();
      load(l.getAttribute("href"));
    });
  });

  // support back/forward
  window.addEventListener("popstate", e=>{
    if(e.state && e.state.url) load(e.state.url, false);
  });

  // load default
  load('page_songs.php', false);
});
