document.addEventListener("DOMContentLoaded", () => {

  const ordersButton = document.getElementById("orders");
  const eventsButton = document.getElementById("events");
  const content = document.getElementById("content");

  ordersButton.addEventListener("click", showOrderButtons);
  eventsButton.addEventListener("click", showEventButtons);
});

// FUNCIONES PEDIDOS
function showOrderButtons() {
  const content = document.getElementById("content");
  content.innerHTML = `
      <button id="pending-orders">Pedidos pendientes</button>
      <button id="paid-orders">Pedidos pagados</button>
      <button id="shipped-orders">Pedidos enviados</button>
      <button id="cancelled-orders">Pedidos cancelados</button>
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

    orders.data.forEach((order) => {
      const orderElement = createOrderElement(order, status, includeActions);
      content.appendChild(orderElement);
    });
  } catch (error) {
    content.innerHTML += `<p style="color:red;">Error al cargar pedidos</p>`;
  }
}

function createOrderElement(order, status, includeActions = false) {
  const orderElement = document.createElement("div");
  orderElement.classList.add("order");
  orderElement.innerHTML = `
      <h3>ID Pedido: ${order.id}</h3>
      <p>Fecha: ${order.order_date}</p>
      <p>Dirección envío: ${order.address}</p>
      <p>Total: ${order.total}</p>
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
    const response = await fetch(`${API_URL}/orders/${orderId}`, {
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

    events.forEach((event) => {
      const eventElement = createEventElement(event, status, includeActions);
      content.appendChild(eventElement);
    });
  } catch (error) {
    content.innerHTML += `<p style="color:red;">Error al cargar eventos</p>`;
  }
}

function createEventElement(event, status, includeActions = false) {
  const eventElement = document.createElement("div");
  eventElement.classList.add("event");
  eventElement.innerHTML = `
      <h3>Título: ${event.title}</h3>
      <p>Descripción: ${event.description}</p>
      <p>Teléfono: ${event.phone}</p>
      <p>Fecha: ${event.date}</p>
      <p>Ubicación: ${event.location}</p>
      <p>Horario: ${event.schedule}</p>
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
    const response = await fetch(`${API_URL}/events/${eventId}`, {
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
