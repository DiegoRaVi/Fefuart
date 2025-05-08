// js/auth.js

// Guardar token y role
function saveAuthData(token, role) {
    localStorage.setItem('token', token);
    localStorage.setItem('role', role);
}

// Obtener token
function getToken() {
    return localStorage.getItem('token');
}

// Obtener role
function getUserRole() {
    return localStorage.getItem('role');
}

// Borrar todo (logout)
function removeAuthData() {
    localStorage.removeItem('token');
    localStorage.removeItem('role');
}

// ¿Está autenticado?
function isAuthenticated() {
    return !!getToken();
}

// Redireccionar si no está autenticado
function redirectIfNotAuthenticated() {
    if (!isAuthenticated()) {
        window.location.href = '/app/Client/views/login.html';
    }
}

// Redireccionar si ya está autenticado
function redirectIfAuthenticated() {
    if (isAuthenticated()) {
        window.location.href = '/app/Client/views/index.html';
    }
}
