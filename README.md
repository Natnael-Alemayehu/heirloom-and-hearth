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
| Caching hint | public GET responses caeey `Cache-Contrl:public, max-age=60` |

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

