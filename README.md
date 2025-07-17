# Dhru Fusion Pro Payment Gateway

This repository contains a **Payment Gateway Aggregation System** that allows the **Dhru Fusion Pro** platform to interact with third-party payment providers like PayPal, Razorpay, etc. The system routes payment-related requests from Dhru Fusion to external gateways and processes callbacks or notifications to update payment statuses in Dhru Fusion.

---

## Project Overview

### Why Payment Gateway Aggregation?
The system acts as a bridge between:
1. **Dhru Fusion Pro System**: The platform initiates requests to this gateway.
2. **Third-Party Payment Providers**: The gateway communicates with external payment services to ensure payment execution.
3. **Callback Mechanism to Dhru Fusion**: After processing the order, payment updates/callbacks are sent back to Dhru Fusion for synchronization.

### Key Features:
1. **Order Management Workflow**:
    - API endpoints to create orders, retrieve their details, and validate payment notifications.
    - Enables seamless integration with multiple third-party gateways.
2. **Third-Party Payment Integration**:
    - Configurable integration with external payment gateways for redirection or hosted payments.
    - Supports success/failure notifications and callback handling.
3. **IPN Notification Processing**:
    - Handles real-time notifications from payment providers about the status of payment transactions.
4. **Validation Mechanisms**:
    - Secure input validations, checksum matching, and order verification to maintain data integrity across requests.

---

## Architecture

### Workflow Diagram:
This aggregated system functions as follows:
```plaintext
1. Dhru Fusion Initiates API Endpoint Calls to the Payment Gateway (`/?action=create_order`, `ipn`, `get_order`)
   ↓
2. Gateway Processes Requests:
   - Creates Orders in the local database.
   - Sends requests to third-party payment providers.
   - Handles received callbacks/notifications.
   ↓
3. Updates Dhru Fusion with the Final Order Details via IPN Callback.
```

### REST API Endpoints:
| Endpoint Action | Method | Description                                                        |
|-----------------|--------|--------------------------------------------------------------------|
| `create_order`  | `POST` | Used to create a new payment order and initiate third-party payment workflows. |
| `ipn`           | `POST` | Processes incoming IPN callbacks from third-party payment gateways. |
| `get_order`     | `GET`  | Retrieves details of an existing order for validation or reference. |

---

## File Structure

### Key Project Files:

1. **API Endpoint Handlers**
    - `create_order.php`: Initiates an order, interacts with third-party payment providers, and provides back the payment checkout URL.
    - `ipn.php`: Processes notifications to update payment statuses and finalizes callbacks to Dhru Fusion.
    - `get_order.php`: Serves as an endpoint to fetch order details for verification.

2. **Core Application Files**
    - `index.php`: Main router that directs incoming API requests to respective handlers.
    - `OrderModel.php`: Core file responsible for interacting with the database for CRUD operations on orders.

3. **Database Layer**
    - `database.php`: Provides the database connection logic (supports both MySQL and SQLite). Handles creating the required `orders` table and managing schema initialization.

4. **API Key Management**
    - `api_keys.php`: Stores and manages the list of allowed API keys for validating requests to the system.

---

## API Endpoint Details

### 1. **Create Order**
- **Endpoint**: `/create_order`
- **Method**: `POST`
- **Description**:  
  Allows Dhru Fusion to create an order, which will be transmitted to a third-party payment provider. Returns an Order ID and a checkout URL.

- **Request Body (JSON)**:
  ```json
  {
    "amount": 200,
    "currency_code": "USD",
    "description": "Test Payment",
    "customer_name": "John Doe",
    "customer_email": "john.doe@example.com",
    "custom_id": "ABC123",
    "ipn_url": "https://example.com/ipn",
    "success_url": "https://example.com/success",
    "fail_url": "https://example.com/fail"
  }
  ```

- **Response**:
  ```json
  {
    "status": "success",
    "message": "Order created successfully.",
    "data": {
      "order_id": 123,
      "checkout_url": "https://sandbox.paypal.com/checkout?token=..."
    }
  }
  ```

### 2. **IPN (Instant Payment Notification)**
- **Endpoint**: `/ipn`
- **Method**: `POST`
- **Description**:  
  Handles notifications from third-party payment systems. Verifies the payment status and updates the order in the system.

- **Required Parameters**:
  ```json
  {
    "checksum": "<checksum-value>",
    "order_id": 123,
    "payment_status": "Paid",
    "received_amount": 200,
    "transaction_id": "TXN123456789"
  }
  ```

- **Response**:
  ```json
  {
    "status": "success",
    "message": "IPN processed successfully."
  }
  ```

### 3. **Get Order**
- **Endpoint**: `/get_order`
- **Method**: `GET`
- **Description**:  
  Fetches order details for validation or reference.

- **Request Parameters**:
    - `order_id`: The unique ID of the order.

- **Response**:
  ```json
  {
    "status": "success",
    "message": "Order details fetched successfully!",
    "data": {
      "order_id": "123",
      "amount": 200,
      "currency_code": "USD",
      "custom_id": "ABC123",
      "status": "Paid",
      "received_amount": 200,
      "transaction_id": "TXN123456789",
      "order_date": "2024-03-01 12:00:00"
    }
  }
  ```

---

## Testing the Endpoints

### Using Postman
You can use the following Postman collection to test all the API endpoints:

**[Postman Link - Dhru Fusion PGDK](https://documenter.getpostman.com/view/23443351/2sB2cShPhW)**

Steps:
1. Open the shared collection link.
2. Import the collection into Postman.
3. Ensure you configure the environment with the correct base URL of the API server (e.g., `https://payment.example.com`).
4. Test `/?action=create_order`, `/?action=ipn`, and `/?action=get_order` using the predefined requests in the collection.

---

## Security Best Practices

- Always validate all incoming inputs and sanitize data before processing.
- Use HTTPS for all API requests to ensure secure communication.
- Rotate API keys periodically and restrict access based on IP or other security policies.
- Ensure sensitive data (like database credentials) is stored securely and not exposed in public repositories.

---

## Error Responses

| HTTP Code | Description                              |
|-----------|------------------------------------------|
| 200       | Request Successful                      |
| 400       | Invalid Input (e.g., missing parameters) |
| 404       | Resource Not Found                      |
| 500       | Internal Server Error                   |

Sample error response:
```json
{
  "status": "error",
  "message": "Invalid checksum",
  "data": null
}
```

---

## Conclusion

This system provides a robust solution for integrating third-party payment gateways with the Dhru Fusion Pro platform. With features like order aggregation, callback handling, and seamless communication mechanisms, it ensures secure and efficient handling of payment workflows.

---
