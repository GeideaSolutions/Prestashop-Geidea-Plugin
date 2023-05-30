const startV2HPP = (data) => {
  console.log("Session create API response", data);
  function onSuccess(data) {
    console.log("handle successful callback as desired, data", data);
    const parseResult = new DOMParser().parseFromString(
      window.paymentObject.returnUrl,
      "text/html"
    );
    const returnUrl = parseResult.documentElement.textContent;
    window.location.href = returnUrl;
  }

  function onError(data) {
    console.log("handle failure callback as desired, data", data);
    setTimeout(function () {
      document.location.href = window.paymentObject.cancelUrl;
    }, 1000);
  }

  function onCancel(data) {
    console.log("handle cancel callback as desired, data:", data);
    setTimeout(function () {
      document.location.href = window.paymentObject.cancelUrl;
    }, 1000);
  }
  try {
    const response = JSON.parse(data);
    if (response.responseCode !== "000") {
      throw data;
    }
    const sessionId = response.session.id; 
    const api = new GeideaCheckout(onSuccess, onError, onCancel);
    api.startPayment(sessionId);
    
  } catch (error) {
    let receivedError;
    const errorFields = [];

    if (error.status && error.errors) {
      const errorsObject = error.errors;

      for (const key of Object.keys(errorsObject)) {
        errorFields.push(key.replace("$.", ""));
      }
      receivedError = {
        responseCode: "100",
        responseMessage: "Field formatting errors",
        detailResponseMessage: `Fields with errors: ${errorFields.toString()}`,
        reference: error.reference,
        detailResponseCode: null,
        orderId: null,
      };
    } else {
      receivedError = {
        responseCode: error.responseCode,
        responseMessage: error.responseMessage,
        detailResponseMessage: error.detailedResponseMessage,
        detailResponseCode: error.detailedResponseCode,
        orderId: null,
        reference: error.reference,
      };
    }
    onError(receivedError);
  }
};