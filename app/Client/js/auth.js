export { getUser, isAuthenticated, getUserRole };

const API_URL = "http://localhost:8000/api";
// js/auth.js

// Guardar token y role
function saveAuthData(token, role) {
  localStorage.setItem("token", token);
  localStorage.setItem("role", role);
}

// Obtener token
function getToken() {
  return localStorage.getItem("token");
}

// Obtener role
function getUserRole() {
  return localStorage.getItem("role");
}

// Borrar todo (logout)
function removeAuthData() {
  localStorage.removeItem("token");
  localStorage.removeItem("role");
}

// ¿Está autenticado?
function isAuthenticated() {
  return !!getToken();
}

// Redireccionar si no está autenticado
function redirectIfNotAuthenticated() {
  if (!isAuthenticated()) {
    window.location.href = "/app/Client/views/login.html";
  }
}

// Redireccionar si ya está autenticado
function redirectIfAuthenticated() {
  if (isAuthenticated()) {
    window.location.href = "/app/Client/views/index.html";
  }
}

async function getUser() {
  let user = null;
  try {
    user = await fetch(API_URL + "/me", {
      method: "GET",
      headers: {
        Authorization: `Bearer ${getToken()}`,
      },
    });
  } catch (e) {
    user = null;
  }

  if (user.ok) {
    return await user.json();
  } else {
    throw new Error("Error fetching user data");
  }
}
