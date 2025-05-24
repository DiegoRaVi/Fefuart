export async function cart(){

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