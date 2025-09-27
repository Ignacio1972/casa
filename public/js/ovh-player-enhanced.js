// Reemplazar branding de AzuraCast por Mediaflow
document.addEventListener('DOMContentLoaded', function() {
    // Reemplazar título de la página
    if (document.title.includes('AzuraCast')) {
        document.title = document.title.replace('AzuraCast', 'Mediaflow');
    }
    
    // Función para reemplazar el footer
    function replaceFooterBranding() {
        const footer = document.querySelector('#footer');
        if (footer && footer.innerHTML.includes('AzuraCast')) {
            footer.innerHTML = 'Powered by <a href="http://51.222.25.222:4000/login.html" target="_blank" style="color: inherit; text-decoration: underline;">Mediaflow</a>';
            return true;
        }
        return false;
    }
    
    // Intentar reemplazar inmediatamente
    if (!replaceFooterBranding()) {
        // Si no funciona, usar observador para cambios dinámicos
        const observer = new MutationObserver(function(mutations) {
            if (replaceFooterBranding()) {
                observer.disconnect();
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Desconectar después de 10 segundos
        setTimeout(() => observer.disconnect(), 10000);
    }
});

// Tu código existente del player con controles personalizados
setTimeout(() => {
  const target = document.querySelector('#public-radio-player .card-body');
  if (!target) return;

  const container = document.createElement("div");
  container.style.textAlign = "center";
  container.style.marginTop = "14px";

  // === Botón Adelantar Canción ===
  const skipBtn = document.createElement("button");
  skipBtn.textContent = "Adelantar canción";
  Object.assign(skipBtn.style, {
    padding: "6px 14px",
    fontSize: "14px",
    backgroundColor: "#333",
    color: "#fff",
    border: "1px solid #444",
    borderRadius: "6px",
    cursor: "pointer",
    marginBottom: "10px",
    fontFamily: "inherit"
  });
  skipBtn.onmouseenter = () => { skipBtn.style.backgroundColor = "#444"; };
  skipBtn.onmouseleave = () => { skipBtn.style.backgroundColor = "#333"; };

  const skipMsg = document.createElement("p");
  skipMsg.style.fontSize = "13px";
  skipMsg.style.color = "#ccc";
  skipMsg.style.marginTop = "8px";

  skipBtn.onclick = function () {
    const apiUrl = "https://ovh.rin.fm/api/station/1/backend/skip";
    const apiKey = "5654d6ca9fbbfcfc:4764be500ce14c5bdf7a49db5322f35c";
    skipMsg.textContent = "Enviando solicitud...";

    fetch(apiUrl, {
      method: "POST",
      headers: {
        "X-API-Key": apiKey,
        "Content-Type": "application/json"
      }
    })
    .then(res => {
      if (res.ok) {
        skipMsg.textContent = "✅ Canción adelantada correctamente.";
        setTimeout(() => { skipMsg.textContent = ""; }, 4000);
      } else {
        skipMsg.textContent = "❌ Error al adelantar.";
      }
    })
    .catch(() => {
      skipMsg.textContent = "⚠️ No se pudo conectar al servidor.";
    });
  };

  // === Botones Like / Dislike ===
  const likeBtn = document.createElement("button");
  likeBtn.textContent = "👍 Me gusta";

  const dislikeBtn = document.createElement("button");
  dislikeBtn.textContent = "👎 No me gusta";

  [likeBtn, dislikeBtn].forEach(btn => {
    Object.assign(btn.style, {
      padding: "6px 12px",
      fontSize: "13px",
      margin: "5px",
      backgroundColor: "#333",
      color: "#fff",
      border: "1px solid #444",
      borderRadius: "6px",
      cursor: "pointer",
      fontFamily: "inherit"
    });
    btn.onmouseenter = () => { btn.style.backgroundColor = "#444"; };
    btn.onmouseleave = () => { btn.style.backgroundColor = "#333"; };
  });

  const voteMsg = document.createElement("p");
  voteMsg.style.fontSize = "13px";
  voteMsg.style.color = "#ccc";
  voteMsg.style.marginTop = "8px";

  const stationId = 7;

  function sendReaction(type) {
    voteMsg.textContent = "⏳ Enviando...";
    const artist = document.querySelector(".now-playing-artist")?.textContent.trim();
    const title = document.querySelector(".now-playing-title")?.textContent.trim();

    fetch(`https://radioislanegra.com/wp-json/radio/v1/${type}/${stationId}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ artist, title })
    })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(() => {
      voteMsg.textContent = type === 'like'
        ? "✅ Marcado como Me gusta"
        : "❌ Marcado como No me gusta";
      setTimeout(() => { voteMsg.textContent = ""; }, 4000);
    })
    .catch(() => {
      voteMsg.textContent = "⚠️ Error al enviar la reacción.";
    });
  }

  likeBtn.onclick = () => sendReaction("like");
  dislikeBtn.onclick = () => sendReaction("dislike");

  container.appendChild(skipBtn);
  container.appendChild(skipMsg);
  container.appendChild(likeBtn);
  container.appendChild(dislikeBtn);
  container.appendChild(voteMsg);
  target.appendChild(container);
}, 2000);