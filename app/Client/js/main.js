import { getUser, isAuthenticated, getUserRole } from './auth.js';
import { loadCartItems } from './order.js';

document.addEventListener("DOMContentLoaded", async () => {
  const placeholder = document.getElementById("header-placeholder");
  const footerPlaceholder = document.getElementById("footer-placeholder");

  try {
    const response = await fetch("../components/header.html");
    const responseFooter = await fetch("../components/footer.html");
    const html = await response.text();
    const htmlFooter = await responseFooter.text();
    
    placeholder.innerHTML = html;
    footerPlaceholder.innerHTML = htmlFooter;

    const menuToggle = document.getElementById('menu-toggle');
  const navMenu = document.getElementById('menu');

  if (menuToggle) {
    menuToggle.addEventListener('click', () => {
      navMenu.classList.toggle('active');
    });
  }

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
    let userRole = null;

    if (isAuthenticated()) {
        user = await getUser();
        userRole = getUserRole();
    }else{
      console.log("NO HAY TOKEN");
    }

    const admin = document.getElementById("admin");
    if (user && userRole === "admin" && admin) {
      admin.style.display = "block";
    }
  } catch (err) {
    console.error("Error cargando el header:", err);
  }
});
