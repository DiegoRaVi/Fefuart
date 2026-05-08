document.addEventListener("DOMContentLoaded", () => {
  const ordersButton = document.getElementById("orders");
  const eventsButton = document.getElementById("events");
  const content = document.getElementById("content");

  ordersButton.addEventListener("click", showOrderButtons);
  eventsButton.addEventListener("click", showEventButtons);

  const searchBtn = document.getElementById("searchBtn");
  searchBtn.addEventListener("click", async () => {
    const userEmail = document.getElementById("userSearch").value.trim();
    if (userEmail) {
      const orders = await searchOrdersByUserEmail(userEmail);

      const content = document.getElementById("content");
      content.innerHTML = `<h2>Pedidos de ${userEmail}</h2>`;

      if (!orders.length) {
        content.innerHTML += `<p>No se encontraron pedidos para ese usuario</p>`;
        return;
      }
      
      for (const order of orders) {
        const orderElement = await createOrderElement(
          order,
          order.status
        );
        content.appendChild(orderElement);
      }
    } else {
      alert("Por favor ingrese un email de usuario");
    }
  });
});

// FUNCIONES PEDIDOS
function showOrderButtons() {
  const content = document.getElementById("content");
  content.innerHTML = `
      <button class="btn" id="pending-orders">Pedidos pendientes</button>
      <button class="btn" id="paid-orders">Pedidos pagados</button>
      <button class="btn" id="shipped-orders">Pedidos enviados</button>
      <button class="btn" id="cancelled-orders">Pedidos cancelados</button>
    `;
  document
    .getElementById("pending-orders")
    .addEventListener("click", () => loadOrdersByStatus("pending", true));
  document
    .getElementById("paid-orders")
    .addEventListener("click", () => loadOrdersByStatus("paid"));
  document
    .getElementById("shipped-orders")
    .addEventListener("click", () => loadOrdersByStatus("shipped"));
  document
    .getElementById("cancelled-orders")
    .addEventListener("click", () => loadOrdersByStatus("cancelled"));
}

async function loadOrdersByStatus(status, includeActions = false) {
  const content = document.getElementById("content");
  content.innerHTML = `<h2>Pedidos ${status}</h2>`;

  try {
    const response = await fetch(`${API_URL}/orders/${status}`, {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + localStorage.getItem("token"),
      },
    });

    const orders = await response.json();

    if (!response.ok) {
      content.innerHTML += `<p style="color:red;">${orders.message}</p>`;
      return;
    }

    for (const order of orders.data) {
      const orderElement = await createOrderElement(
        order,
        status,
        includeActions
      );
      content.appendChild(orderElement);
    }
    /*orders.data.forEach((order) => {
      const orderElement = createOrderElement(order, status, includeActions);
      content.appendChild(orderElement);
    });*/
  } catch (error) {
    content.innerHTML += `<p style="color:red;">Error al cargar pedidos</p>`;
  }
}

async function createOrderElement(order, status, includeActions = false) {
  const user = await getUserById(order.user_id);
  const orderElement = document.createElement("div");
  orderElement.classList.add("order");
  orderElement.innerHTML = `
      <h3>ID Pedido: ${order.id}</h3>
      <p><strong>Usuario:</strong> ${user.name || "N/A"}</p>
      <p><strong>Email:</strong> ${user.email || "N/A"}</p>
      <p><strong>Status:</strong> ${order.status || "N/A"}</p>
      <p><strong>Fecha:</strong> ${order.order_date}</p>
      <p><strong>Dirección envío:</strong> ${order.address}</p>
      <p><strong>Total:</strong> ${order.total}</p>
    `;
  const showButton = document.createElement("button");
  showButton.textContent = "Mostrar Productos";
  showButton.addEventListener("click", () =>
    showOrderProducts(order.id, orderElement)
  );
  orderElement.appendChild(showButton);

  if (includeActions) {
    const confirmButton = document.createElement("button");
    confirmButton.textContent = "Terminado";
    confirmButton.addEventListener("click", () =>
      updateOrderStatus(order.id, "shipped", orderElement)
    );

    const rejectButton = document.createElement("button");
    rejectButton.textContent = "Rechazar";
    rejectButton.addEventListener("click", () =>
      updateOrderStatus(order.id, "cancelled", orderElement)
    );

    orderElement.appendChild(confirmButton);
    orderElement.appendChild(rejectButton);
  }

  return orderElement;
}

async function showOrderProducts(orderId, orderElement) {
  // Verifica si ya existe el contenedor de productos
  let productContainer = orderElement.querySelector(".product-container");

  if (productContainer) {
    // Si ya está visible, lo ocultamos
    productContainer.remove();
    return;
  }

  // Creamos el contenedor de productos
  productContainer = document.createElement("div");
  productContainer.classList.add("product-container");
  productContainer.innerHTML = `<h4>Productos del pedido</h4>`;

  try {
    const response = await fetch(`${API_URL}/products/${orderId}`, {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + localStorage.getItem("token"),
      },
    });

    const products = await response.json();

    if (!response.ok) {
      productContainer.innerHTML += `<p style="color:red;">${products.message}</p>`;
    } else {
      products.forEach((product) => {
        const productElement = createProductElement(product);
        productContainer.appendChild(productElement);
      });
    }
  } catch (error) {
    productContainer.innerHTML += `<p style="color:red;">Error al cargar productos</p>`;
  }

  // Añadimos el contenedor al pedido
  orderElement.appendChild(productContainer);
}

function createProductElement(product) {
  const productElement = document.createElement("div");
  const imageUrl = `${IMAGES_URL}/storage/${product.image_url}`;
  productElement.classList.add("product");
  productElement.innerHTML = `
    <p><strong>Nombre:</strong> ${product.name}</p>
    <p><strong>Descripción:</strong> ${product.description}</p>
    <p><strong>Precio:</strong> ${product.price}</p>
    <p><strong>Cantidad:</strong> ${product.quantity}</p>
    <p><strong>Categoría:</strong> ${product.category}</p>
    <p><strong>Subcategoría:</strong> ${product.subcategory}</p>
    <p><strong>Tipo Envío:</strong> ${product.delivery_type}</p>
    <p><strong>Tiempo Envío:</strong> ${product.delivery_time}</p>
    <img src='${imageUrl}' alt='${product.name}' style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;"/>
    <hr/>
  `;
  return productElement;
}

async function updateOrderStatus(orderId, newStatus, container) {
  try {
    const response = await fetch(`${API_URL}/admin/orders/${orderId}`, {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + localStorage.getItem("token"),
      },
      body: JSON.stringify({ status: newStatus }),
    });

    if (response.ok) {
      container.innerHTML += `<p style="color:green;">Pedido marcado como ${newStatus}</p>`;
    } else {
      container.innerHTML += `<p style="color:red;">Error al actualizar el pedido</p>`;
    }
  } catch (error) {
    container.innerHTML += `<p style="color:red;">Error en la petición</p>`;
  }
}

// FUNCIONES EVENTOS
function showEventButtons() {
  const content = document.getElementById("content");
  content.innerHTML = `
      <button id="pending-events">Eventos pendientes</button>
      <button id="confirmed-events">Eventos confirmados</button>
      <button id="rejected-events">Eventos rechazados</button>
      <button id="done-events">Eventos completados</button>
    `;

  document
    .getElementById("pending-events")
    .addEventListener("click", () => loadEventsByStatus("pending", true));
  document
    .getElementById("confirmed-events")
    .addEventListener("click", () => loadEventsByStatus("confirmed"));
  document
    .getElementById("rejected-events")
    .addEventListener("click", () => loadEventsByStatus("rejected"));
  document
    .getElementById("done-events")
    .addEventListener("click", () => loadEventsByStatus("done"));
}

async function loadEventsByStatus(status, includeActions = false) {
  const content = document.getElementById("content");
  content.innerHTML = `<h2>Eventos ${status}</h2>`;

  try {
    const response = await fetch(`${API_URL}/events/${status}`, {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + localStorage.getItem("token"),
      },
    });

    const events = await response.json();

    if (!response.ok) {
      content.innerHTML += `<p style="color:red;">${events.message}</p>`;
      return;
    }

    for (const event of events) {
      const eventElement = await createEventElement(
        event,
        status,
        includeActions
      );
      content.appendChild(eventElement);
    }

    /*events.forEach((event) => {
      const eventElement = createEventElement(event, status, includeActions);
      content.appendChild(eventElement);
    });*/
  } catch (error) {
    content.innerHTML += `<p style="color:red;">Error al cargar eventos</p>`;
  }
}

async function createEventElement(event, status, includeActions = false) {
  const user = await getUserById(event.user_id);
  const eventElement = document.createElement("div");
  eventElement.classList.add("event");
  eventElement.innerHTML = `
      <h3>Título: ${event.title}</h3>
      <p><strong>Usuario:</strong> ${user.name}</p>
      <p><strong>Email:</strong> ${user.email}</p>
      <p><strong>Descripción:</strong> ${event.description}</p>
      <p><strong>Teléfono:</strong> ${event.phone}</p>
      <p><strong>Fecha:</strong> ${event.date}</p>
      <p><strong>Ubicación:</strong> ${event.location}</p>
      <p><strong>Horario:</strong> ${event.schedule}</p>
    `;

  if (includeActions) {
    const confirmButton = document.createElement("button");
    confirmButton.textContent = "Confirmar";
    confirmButton.addEventListener("click", () =>
      updateEventStatus(event.id, "confirmed", eventElement)
    );

    const rejectButton = document.createElement("button");
    rejectButton.textContent = "Rechazar";
    rejectButton.addEventListener("click", () =>
      updateEventStatus(event.id, "rejected", eventElement)
    );

    eventElement.appendChild(confirmButton);
    eventElement.appendChild(rejectButton);
  }

  return eventElement;
}

async function updateEventStatus(eventId, newStatus, container) {
  try {
    const response = await fetch(`${API_URL}/admin/events/${eventId}`, {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
        Authorization: "Bearer " + localStorage.getItem("token"),
      },
      body: JSON.stringify({ status: newStatus }),
    });

    if (response.ok) {
      container.innerHTML += `<p style="color:green;">Evento ${
        newStatus === "confirmed" ? "confirmado" : "rechazado"
      }</p>`;
    } else {
      container.innerHTML += `<p style="color:red;">Error al actualizar el evento</p>`;
    }
  } catch (error) {
    container.innerHTML += `<p style="color:red;">Error en la petición</p>`;
  }
}

async function getUserById(id) {
  try {
    const response = await fetch(API_URL + "/user/" + id, {
      method: "GET",
      headers: {
        Authorization: "Bearer " + localStorage.getItem("token"),
        "Content-Type": "application/json",
      },
    });

    const data = await response.json();

    if (!response.ok) {
      console.error(
        "Error de API al obtener el usuario:",
        data.message || response.status
      );
      return { name: "Usuario desconocido" }; // Fallback visible
    }

    return data;
  } catch (e) {
    console.error("Error en fetch getUserById:", e);
    return { name: "Error" };
  }
}

async function searchOrdersByUserEmail(email) {
  try {
    console.log(
      "Llamando a:",
      `${API_URL}/orders/search?email=${encodeURIComponent(email)}`
    );
    const response = await fetch(
      `${API_URL}/orders/search?email=${encodeURIComponent(email)}`,
      {
        method: "GET",
        headers: {
          Authorization: "Bearer " + localStorage.getItem("token"),
          "Content-Type": "application/json",
        },
      }
    );

    const data = await response.json();
    console.log("Respuesta del backend: ", data);

    if (!response.ok) {
      alert(data.message || "No se pudieron obtener las órdenes");
      return [];
    }

    if (Array.isArray(data)) {
      return data;
    }

    if (Array.isArray(data.orders)) {
      return data.orders;
    }

    return [];
  } catch (error) {
    console.error("Error en búsqueda:", error.message);
    alert(`Error al obtener las órdenes: ${error.message}`);
    return [];
  }
}
