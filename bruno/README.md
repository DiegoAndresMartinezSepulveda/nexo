# Colección Bruno de Nexo

## Abrir la colección

Clona la rama de Bruno:

```bash
git clone -b bruno-nexo-collection https://github.com/DiegoAndresMartinezSepulveda/nexo.git
```

En Bruno usa **Open Collection** y abre:

```text
nexo/bruno/Sample API Collection
```

Selecciona el entorno **Nexo Hostinger** y configura tus propios valores de `token` y `xsrfToken`. No se comparten credenciales.

## Sincronizar cambios

Para recibir cambios:

```bash
git pull
```

Para guardar y subir cambios:

```bash
git add bruno
git commit -m "Actualizar colección Bruno"
git push origin bruno-nexo-collection
```

La colección usa la API real de Nexo en Hostinger.