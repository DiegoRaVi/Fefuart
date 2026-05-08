export async function cart() {
  const cartResponse = await fetch(API_URL + "/cart-order", {
    method: "GET",
    headers: {
      Authorization: `Bearer ${localStorage.getItem("token")}`,
    },
  });

  if (cartResponse.ok) {
    const cartOrder = await cartResponse.json();
    return cartOrder;
  } else if (cartResponse.status === 404) {
    const newOrderResponse = await fetch(API_URL + "/cart-order", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${localStorage.getItem("token")}`,
      },
      body: JSON.stringify({
        address: "Dirección predeterminada",
        total: 0,
      }),
    });

    if (newOrderResponse.ok) {
      const newOrder = await newOrderResponse.json();
      return newOrder;
    } else {
      throw new Error("No se pudo crear una nueva cart order");
    }
  } else {
    throw new Error("Error al obtener la cart order");
  }
}

export async function loadCartItems() {
  try {
    const cartItemsList = document.getElementById("cart-items");

    if (!cartItemsList) {
      console.warn("Elemento cart-items no encontrado");
      return;
    }

    cartItemsList.innerHTML = "";

    // 1. Obtener la orden del carrito
    const cartResponse = await fetch(`${API_URL}/cart-order`, {
      method: "GET",
      headers: {
        Authorization: "Bearer " + localStorage.getItem("token"),
      },
    });

    if (!cartResponse.ok) {
      cartItemsList.innerHTML = "<li>No hay productos en el carrito</li>";
      return;
    }

    const cartOrder = await cartResponse.json();
    console.log("Orden del carrito:", cartOrder);

    // 2. Obtener productos de la orden
    const productsResponse = await fetch(
      `${API_URL}/products/${cartOrder.id}`,
      {
        method: "GET",
        headers: {
          Authorization: "Bearer " + localStorage.getItem("token"),
        },
      }
    );

    if (!productsResponse.ok) {
      cartItemsList.innerHTML = "<li>Todavía no hay productos</li>";
      return;
    }

    const products = await productsResponse.json();

    if (products.length === 0) {
      cartItemsList.innerHTML = "<li>No hay productos en el carrito</li>";
      return;
    }

    // 3. Limpiar y mostrar productos
    cartItemsList.innerHTML = "";
    products.forEach((product) => {
      const li = document.createElement("li");
      li.innerHTML = `
        <p>${product.name} - ${product.price}€</p>
        <button class="remove-btn btn" data-product-id="${product.id}">Eliminar</button>
      `;
      cartItemsList.appendChild(li);
    });
    const buttonDetails = document.createElement("button");
    buttonDetails.textContent = "Detalles";
    buttonDetails.className = "btn";
    buttonDetails.addEventListener("click", () => {
      location.href = "./cart.html";
    });
    cartItemsList.appendChild(buttonDetails);

    // 4. Añadir event listeners a los botones
    document.querySelectorAll(".remove-btn").forEach((button) => {
      button.addEventListener("click", async () => {
        const productId = button.dataset.productId;
        const success = await deleteProduct(productId);
        if (success) {
          await loadCartItems(); // Recargar solo si se eliminó correctamente
        }
      });
    });
  } catch (error) {
    console.error("Error:", error);
    const cartItemsList = document.getElementById("cart-items");
    if (cartItemsList) {
      cartItemsList.innerHTML = "<li>Error al cargar el carrito</li>";
    }
  }
}

// Función para modificar una order
export async function patchOrder(orderId, orderTotal) {

  const response = await fetch(`${API_URL}/orders/${orderId}`, {
    method: "PATCH",
    headers: {
      "Content-Type": "application/json",
      Authorization: "Bearer " + localStorage.getItem("token"),
    },
    body: JSON.stringify({ total: orderTotal }),
  });

  if (!response.ok) {
    console.error("No se pudo actualizar el pedido");
    return false;
  }

  return true;
}

// Función para eliminar producto
export async function deleteProduct(productId) {
  try {
    const response = await fetch(`${API_URL}/products/${productId}`, {
      method: "DELETE",
      headers: {
        Authorization: "Bearer " + localStorage.getItem("token"),
      },
    });

    if (!response.ok) {
      console.error("Error al eliminar producto");
      return false;
    }

    return true;
  } catch (error) {
    console.error("Error:", error);
    return false;
  }
}
