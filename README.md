# Heirloom & Hearth - Farm To Table Provenance API

---

## Table of Contents

1. [overview](#overview)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Data Architecture](#data-architecture)
5. [API Reference](#api-reference)
    - [Authentication](#authentication)
    - [GET /hh/v1/sourcing](#get-hhv1-sourcingid)
    - [GET /hh/v1/sourcing/{id}](#get-hhv1sourcingid)
    - [POST /hh/v1/sourcing/{id}/stock](#post-hhv1sourcingidstock)
6. [Supplier Account Setup](#supplier-account-setup)
7. [Error Reference](#error-reference)
8. [Wordpress Coling Standards](#wordpress-coding-standards)

---

## Overview

The plugin registers two custom Post Types (**Suppliers** and **Daily Ingredients**), a custom `hh_supplier` Wordpress user role, and a fully namespaced REST API at `/hh/v1/`. Key features:

|Feature | Detail|
|---|---|
| Namespace | `hh/v1` |
| Auth method | Wordpress Application Passwords (RFC 7617 Basic Auth) |
| Stock Status | `available`.`low-stock`.`out_of_season` |
| Ownership enforcement | Suppliers can only update their own ingredients | 
| Auto-timestamp | `_hh_last_updated` refreshes on every stock change |
| Caching hit | public GET responses caeey `Cache-Contrl:public, max-age=60` |

---

## Requirements

- Wordpress **6.0+**
- PHP **8.0+**
- Application Passwords feature enabled (default since WP 5.6; requires HTTPS in production)

---
## Installation

### From ZIP
1. Download `heirloom-hearth.zip`
2. In Wordpress Admin -> **Plugins -> Add New -> Upload Plugin**, upload the ZIP.
3. Click **Active Plugin**.
4. The `hh_supplier` user role is created automatically on deactivation.

#### From source
```bash
git clone https://github.com/Natnael-Alemayehu/heirloom-and-hearth.git
cp -r heirloom-hearth/path/to/wp-content/plugins/
# Then activate via WP Admin or WP-CLI:
wp plugin activate heirloom-hearth 
```

---

## Data Architecture

### CPT: `hh_cupplier`

|Field| Storage| Type | Notes|
|---|---|---|---|
|Farm name | `post_title` | string | required|
|Biography | `post_content` | string | Rich text / Block Editor | 
|Logo | `post_thimbnail` + `_hh_farm_logo_id` | integer (attachment ID) | Thumbnail size served in API |
|Location | `_hh_farm_location` | string | Free-text address/region|
Linked WP user | `_hh_wp_user_id` | integer | Set by admin; used for ownership checks|

### CPT: `hh_ingredient`
|Field|Storage|Type|Notes|
|---|---|---|---|
| Name | `post_totle` | string | Required |
| Category | `hh_ingredient_cat` taxonomy | term | Heirarchical (e.g Vegitables > Root) |
| Stock Status | `_hh_stock_status` | string | `available` \|`low_stock` \|`out_of_season` |
| Linked Supplier | `_hh_supplier_id` | integer (post ID) | FK -> `hh_supplier` |
| Last updated | `_hh_last_updated` | string | ISO-8601 UTC; auto-set, read-only via API | 
| Image | `post_thumbnail` | ingteger (attachment ID) | Medium size served in API |


### Relationships
```
hh_supplier (1) ----------- (N) hh_ingredient
   |                |      
_hh_wp_user_id      _hh_supplier_id
(links to WP user)      (links back to supplier)
```

## API Reference
**Base URL:** `http://hairloom-health.local/wp-json`

All responses are JSON and follow this envelope:
```json
{
    "success" : true,
    "...data..."
}
```

Errors follow the standard WP REST error shape:

```json
{
    "code": "hh_rest_not_logged_in",
    "message": "Authentication required",
    "data": {"status", 401}
}
```

---

### Authentication

The **read endpoints** (`GET`) are **public** - no credentials required.
The **write endpoint** (`POST .../stock`) requires **Wordpress Application Passwords** delivered as HTTP Basic Auth"
```
Authorization: Basic base64(username:application_password)
```
#### Generating an Application Password
1. In WP Admin navigate to ***Users->Profile** for the supplier account.
2. Scroll to **Application Passwords**.
3. Enter a name (e.g., "Bistro Mobile App") and click **Add New Application Password**.
4. Copy the generated password(shown only once).
5. Combine with the WP username and Base64-encode:

```bash
# Example - never share this value
echo -m "jane_farm:ABCD EFGH IJKL MNOP QRST UVWX" | base64
# -> LW0gamFuZV9mYXJtOkFCQ0QgRUZHSCBJSktMIE1OT1AgUVJTVCBVVldYCg==
```
Then include in every write request:
```
Authorization: Basic LW0gamFuZV9mYXJtOkFCQ0QgRUZHSCBJSktMIE1OT1AgUVJTVCBVVldYCg==
Content-Type: application/json
```
> **Security note:** always use HTTPS. Application Passwords are transmitted in every request header, so plain HTTP would expose credentials.

---

#### GET /hh/v1/sourcing
Returns all published ingredients with fully nested supplier profiles.

**URL**
```
GET /wp-json/hh/v1/sourcing
```

**Query Parameters**
|Parameter | Type | Required | Values | Description |
|---|---|---|---|---|
|`status` | string | No | `available` `low_stock` `out_of_season` | filter by stock status |
|`supplier_id` | integer | No any valid post ID | Filter to a single farm |
|`category` | string | No | taxonomy term slug | filter by ingredient category | 

```bash
curl -X GET "http://hairloom-health.local/wp-json/hh/v1/sourcing?status=available"\
    -H "Accept: application/json"
```

**Example Response - 200 OK**
```json
{
    "success": true,
    "count": 0,
    "filter": {
        "status": "available"
    },
    "ingredients": []
}
```

---

### GET /hh/v1/sourcing/{id}

Returns a single ingredient by its Wordpress post ID.

**URL**

```
GET /wp-json/hh/v1/sourcing/{id}
```

**Path Paramters**
| Paramter | Type | Required | Description |
|---|---|---|---|
|`id` | integer | Yes | The `hh_ingredient` postID |

**Example Request**
```bash
curl -X GET "http://hairloom-health.local/wp-json/hh/v1/sourcing/42"\
    -H "Accept: application/json"
```

**Example Response - 200 OK**
```json
{
    "success": true,
    "ingredient": {
        "id": 8,
        "name": "First Ingredient",
        "slug": "first-ingredient",
        "categories": [
            {
                "id": 3,
                "name": "Ethiopian",
                "slug": "ethiopia"
            }
        ],
        "stock_status": "available",
        "last_updated": "2026-04-07T09:46:48Z",
        "image": null,
        "supplier": null
    }
}
```

**Error - 404 Not Found**
```json
{
    "code":"hh_ingredient_not_found",
    "message":"ingredient not found",
    "data": {"status", 404}
}
```

### POST /hh/v1/sourcing/{id}/stock

Updates the stock status of a specific ingredient. 
>**Authentication required** The autnenticated user must hold the `hh_supplier` role and mist be linked (via `_hh_wp_user_id` on the supplier post) to the ingredient's supplier 

**URL**

```
POST /wp-json/hh/v1/sourcing/{id}/stock
```

**Path Parameters**
|Parameter | Type | Required | Description |
|---|---|---|---|
|`id`| integer | Yes | The `hh_ingredient` post ID |

**Request Body**(JSON)

| Field | Type | Required | Values | Description |
|---|---|---|---|---|
|`stock_status` | string | Yes | `available` `low_stock` `out_of_season` | New stock level |

**Example Request**
```bash
curl -X POST "http://hairloom-health.local/wp-json/hh/v1/sourcing/42/stock"\
    -H "Authorization: Basic LW0gamFuZV9mYXJtOkFCQ0QgRUZHSCBJSktMIE1OT1AgUVJTVCBVVldYCg==" \
    -H "Content-Type: Application/json"\
    -d '{"stock_status": "low_stock"}'
```

**Example Response - 200 OK**
```json

```

**Error - 401 Unauthorized**
```json

```

**Error - 403 Forbidden (wrong supplier)**
```json
{
    "code": "hh_rest_forbidden",
    "message": "You do not have permission to update stock.",
    "data": {
        "status": 403
    }
}
```

## Supplier Account Setup

Follow these steps to onboard a new farm supplier:
1. **Create a wordpress user**
    - Go to **users->Add New**.
    - Set the role to **Supplier**.
    - Note the new user's ID (visible in the URL bat when editing the user).
2. **Create the Supplier post**
    - Go to **Suppliers -> Add New**.
    - Fill in the farm name (title), biography (body), location meta field, and upload a logo.
    - **Save/Publish** the post. 
    - Open the post edit screen again; the post ID is visible in the URL (`post=XX`).
3. **Link the WP user to the Supplier post**
    - In the supplier post's Custom Fields panel (or via the block editor sidebar), add the field `_hh_wp_user_id` with the WP user ID from step 1.
    - Save the post.
4. **Create Ingredient posts for this supplier**
    - Go to **Daily Ingredients -> Add New**.
    - Set the `_hh_supplier_id` meta field to the Supplier post ID.
    - Set the initial `_hh_stock_status` (defaults to `available`).
    - Save/Publish
5. **issue the supplier their Application Password**
    - Navigate to **Users -> {Supplier's user} -> Profile**.
    - Under **Application Passwords**, generate a password named "Bistro API".
    - Securely share the `username:application_password` pair with the farm.
    - Provide them with the POST endpoint URL and the JSON payload format above.

---

## Error Reference
|Code| HTTP | Meaning|
|---|---|---|
|`hh_rest_not_logged_in` | 401 | No credentials or invalid credentials |
|`hh_rest_forbidden` | 403 | Authenticated but missing `hh_update_stock` capability | 
|`hh_rest_ownership_denied` | 403 | Supplier attempting to update another farm's ingrerient | 
|`hh_ingredient_not_found` | 404 | No published `hh_ingredient` at the given ID |
|`hh_invalid_ingredient_id` | 400 | ID parameter is not valid ingredient post | 
|`hh_invalid_param` | 400 | A request parameter failed scheme validation |

---

## Wordpress Coding Standards
This plugin follows [Wordpress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)(WPCS):

- All user-supplied input is sanitized with `sanitize_key()`, `sanitize_text_field()`, or `absint()` before use.
- All output is escaping at the point of output. 
- Nonces are not used on REST endpoints (WP REST auth replaces nonce-based auth for REST).
- No direct `$wpdb` queries are used; all data sccess goes through `WP_Query`, `get_post_meta()`, and `update_post_meta()`.
- Namespaced PHP (`namespace HairloomHearth`) to avoid global function/ classes collitions.
- Text-domain `heirloom-hearth` applied to all translatable strings.   