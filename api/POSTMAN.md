# Colección de Postman - AS CRM 2026

## Configuración de Entorno
Crea un **Environment** en Postman con la variable:
- `base_url`: `http://localhost/as-crm-2026/api/public`

---

## Endpoints

### 1. Health Check (Verificar estado)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/health`
- **Body**: None

### 2. Login (Iniciar Sesión)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/auth/login`
- **Headers**: 
  - `Content-Type`: `application/json`
- **Body** (Raw JSON):
  ```json
  {
    "email": "test@arrendamientoseguro.app",
    "password": "password123"
  }
  ```

### 3. Get User Profile (Perfil de Usuario)
- **Method**: `GET`
- **URL**: `{{base_url}}/api/v1/users/me`
- **Headers**:
  - `Authorization`: `Bearer <Pegar AccessToken Aquí>`

### 4. Refresh Token (Renovar Sesión)
- **Method**: `POST`
- **URL**: `{{base_url}}/api/v1/auth/refresh`
- **Headers**: `Content-Type: application/json`
- **Body** (Raw JSON):
  ```json
  {
    "refreshToken": "<Pegar RefreshToken Aquí>"
  }
  ```
