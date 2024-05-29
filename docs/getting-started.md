# Getting Started

The DbpRelayAuthorizationBundle provides 2 APIs:
* the [PHP Backend API](./php-api.md)
* the [REST Web API](./rest-api.md)

The PHP backend API is used to 
* register the app resources you want to control access to (like documents, products, ...), at the time they are created
* query the logged-in user's access rights to those resources, at the time they are requested

The REST Web API can be used to 
* create/delete/request user **groups**
* create/delete/request **members** (users, or subgroups) of groups
* create/delete/request **_resource action grants_** for users/groups to perform certain actions on the registered resources. 