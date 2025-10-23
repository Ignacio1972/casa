# DEVELOPER QUICK START - CLAUDE AI MULTI-CLIENT

## 🚀 SETUP RÁPIDO (5 minutos)

### 1. Verificar que el sistema actual funciona
```bash
# Test basic Claude API
curl -X POST "http://localhost:4000/api/claude-service.php" \
-H "Content-Type: application/json" \
-d '{"action": "generate", "category": "ofertas", "context": "Test"}'

# Should return: {"success":true,"suggestions":[...]}
```

### 2. Crear archivo base de configuración
```bash
# Crear clients-config.json
cat > /var/www/casa/src/api/data/clients-config.json << 'EOF'
{
  "default_client": "casa_costanera",
  "clients": {
    "casa_costanera": {
      "id": "casa_costanera",
      "name": "Casa Costanera",
      "context": "Eres un experto creando anuncios para Casa Costanera, un moderno centro comercial en Chile con más de 100 tiendas, restaurantes de primera clase y entretenimiento familiar.",
      "active": true,
      "created_at": "2025-09-11T00:00:00Z",
      "category": "centro_comercial"
    },
    "generic": {
      "id": "generic", 
      "name": "Cliente Genérico",
      "context": "Eres un experto en crear anuncios comerciales efectivos y atractivos.",
      "active": true,
      "created_at": "2025-09-11T00:00:00Z",
      "category": "generico"
    }
  }
}
EOF

chmod 644 /var/www/casa/src/api/data/clients-config.json
```

## 📝 ORDEN DE IMPLEMENTACIÓN RECOMENDADO

### PASO 1: Backend API (30 min)
```bash
# 1. Crear clients-service.php
# 2. Modificar claude-service.php (método getSystemPrompt)
# 3. Test endpoints con curl
```

### PASO 2: Core Service (30 min)  
```bash
# 1. Crear client-context-service.js
# 2. Modificar llm-service.js (buildContext method)
# 3. Test desde browser console
```

### PASO 3: Dashboard UI (45 min)
```bash
# 1. Modificar ai-suggestions.js (agregar selector)
# 2. Test selector functionality
# 3. Verify persistence
```

### PASO 4: Playground Admin (60 min)
```bash
# 1. Enhance claude.html with CRUD
# 2. Add context editor
# 3. Test full workflow
```

## 🔧 SNIPPETS ÚTILES

### Test rápido de contextos
```javascript
// Browser console test
fetch('/api/clients-service.php?action=list_clients')
  .then(r => r.json())
  .then(console.log);
```

### Debug Claude calls
```php
// Agregar al claude-service.php para debug
error_log('Claude Request: ' . json_encode($requestData));
error_log('Claude Response: ' . json_encode($responseData));
```

### localStorage debug
```javascript
// Browser console
localStorage.getItem('selected_client_id');
localStorage.setItem('selected_client_id', 'generic');
```

## 🐛 TROUBLESHOOTING COMÚN

### Error: "Client not found"
```bash
# Verificar que clients-config.json existe y es válido
cat /var/www/casa/src/api/data/clients-config.json | jq .
```

### Error: "Permission denied"
```bash
# Verificar permisos
ls -la /var/www/casa/src/api/data/clients-config.json
# Should be: -rw-r--r-- www-data www-data
```

### Error: "Claude API not responding"
```bash
# Verificar API key
grep CLAUDE_API_KEY /var/www/casa/.env
```

## 📱 TESTING MATRIX

| Component | Test Method | Expected Result |
|-----------|-------------|-----------------|
| clients-service.php | `curl POST list_clients` | JSON with clients |
| claude-service.php | `curl POST with client_id` | Different suggestions |
| Dashboard selector | Manual UI test | Dropdown populated |
| Persistence | Reload page | Selection maintained |
| Error handling | Invalid client_id | Fallback to default |

## 🔍 DEBUG COMMANDS

```bash
# Check logs
tail -f /var/www/casa/src/api/logs/claude-$(date +%Y-%m-%d).log

# Validate JSON config
jq . /var/www/casa/src/api/data/clients-config.json

# Check API responses
curl -s http://localhost:4000/api/clients-service.php?action=list_clients | jq .

# Monitor network calls in browser
# F12 > Network > Filter: claude
```

## 🎯 VALIDATION CHECKLIST

- [ ] clients-config.json created and valid
- [ ] clients-service.php returns proper JSON
- [ ] claude-service.php accepts client_id parameter  
- [ ] Dashboard shows client selector
- [ ] Selection persists on page reload
- [ ] Different clients generate different suggestions
- [ ] Error handling works (invalid client, API down, etc.)
- [ ] Playground CRUD functions work
- [ ] No console errors in browser
- [ ] Backward compatibility maintained

## ⚠️ GOTCHAS A EVITAR

1. **No usar `require_once` en clients-service.php** - usar `include_once` para evitar fatal errors
2. **Siempre validar JSON** antes de parse en PHP
3. **Escapar output** en HTML para evitar XSS
4. **Manejar race conditions** en localStorage
5. **No asumir que clients-config.json existe** - crear si no existe
6. **Validar client_id** antes de usarlo como key de array
7. **Cache busting** después de cambios en config JSON

## 🚀 DEPLOYMENT NOTES

```bash
# Production deployment
rsync -av /var/www/casa/src/api/data/clients-config.json user@prod:/var/www/casa/src/api/data/
service nginx reload
service php8.1-fpm reload

# Backup before deployment
cp /var/www/casa/src/api/data/clients-config.json /var/www/casa/backups/clients-config-$(date +%Y%m%d).json
```

**¡READY TO CODE!** 🎉