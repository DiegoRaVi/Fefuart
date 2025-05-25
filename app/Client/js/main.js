import { getUser, isAuthenticated } from './auth.js';
import { loadCartItems } from './order.js';

document.addEventListener("DOMContentLoaded", async () => {
  const placeholder = document.getElementById("header-placeholder");

  try {
    const response = await fetch("../components/header.html");
    const html = await response.text();
    placeholder.innerHTML = html;

    // Activar funcionalidad del carrito
    const icon = document.getElementById("cart-icon");
    const panel = document.getElementById("cart-panel");

    if (icon && panel) {
      icon.addEventListener("click", () => {
        panel.classList.toggle("show");
        if (panel.classList.contains("show")) {
          loadCartItems();
        }
      });
    }

    // Comprobar usuario después de cargar el HTML
    let user = null;

    if (isAuthenticated()) {
        user = await getUser();
    }else{
      console.log("NO HAY TOKEN");
    }

    const admin = document.getElementById("admin");
    if (user && user.role === "admin" && admin) {
      admin.style.display = "block";
    }
  } catch (err) {
    console.error("Error cargando el header:", err);
  }
});
