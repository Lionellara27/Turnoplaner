#!/usr/bin/env bash
# ==============================================================================
# TurnoPlaner - pruebas de punta a punta (HU-01 a HU-08) con curl
#
# Recorre los criterios de aceptacion como un usuario real: sesion, CSRF,
# redirecciones, mensajes en pantalla y datos en la base.
#
# ATENCION: REINICIA la base local "turnoplaner" con database/turnoplaner.sql.
#
# Requisitos: XAMPP con MySQL iniciado y el servidor levantado
#   C:\xampp\php\php.exe spark serve
# Uso (Git Bash, desde la carpeta del proyecto):
#   bash tests/e2e/turnoplaner_e2e.sh
# ==============================================================================
RAIZ="$(cd "$(dirname "$0")/../.." && pwd)"
/c/xampp/mysql/bin/mysql.exe -u root < "$RAIZ/database/turnoplaner.sql" || { echo "No se pudo reiniciar la base"; exit 1; }
rm -f "$RAIZ"/writable/cache/*throttler* 2>/dev/null
B=http://localhost:8080
MY=/c/xampp/mysql/bin/mysql.exe
D=$(mktemp -d)
PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); echo "  OK    $1"; }
bad()  { FAIL=$((FAIL+1)); echo "  FALLA $1"; }
check(){ if eval "$2"; then ok "$1"; else bad "$1"; fi; }
sql()  { "$MY" -u root -N -e "$1"; }
tok()  { grep -o 'name="csrf_test_name" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//; s/"//'; }
get()  { curl -s -b "$D/$1" -c "$D/$1" -o "$D/out.html" -w "%{http_code} %{redirect_url}" "$B/$2"; }
post() { local j=$1 url=$2 page=$3; shift 3; curl -s -b "$D/$j" -c "$D/$j" -o "$D/out.html" -w "%{http_code} %{redirect_url}" --data-urlencode "csrf_test_name=$(tok "$page")" "$@" "$B/$url"; }
follow(){ curl -s -L -b "$D/$1" -c "$D/$1" -o "$D/out.html" "$B/$2"; }
login(){ local j=$1 u=$2 p=$3; rm -f "$D/$j"; get "$j" login >/dev/null; cp "$D/out.html" "$D/form.html"; post "$j" login "$D/form.html" --data-urlencode "identificador=$u" --data-urlencode "password=$p"; }
has()  { grep -q -- "$1" "$D/out.html"; }
texto(){ sed -e 's/<[^>]*>//g' "$D/out.html" | tr -s ' \n' ' '; }   # la pagina como texto plano

echo "== Seguridad general"
r=$(curl -s -o /dev/null -w "%{http_code}" -d "identificador=admin@turnoplaner.local&password=admin123" $B/login)
check "Sin token CSRF el login es rechazado (403)" '[[ $r == 403 ]]'
r=$(curl -s -o /dev/null -w "%{http_code}" -X POST $B/registro/guardar)
check "Ruta publica de registro eliminada" '[[ $r == 404 || $r == 403 ]]'
check "Sin sesion /inicio va a login" '[[ $(get anon inicio) == *"/login" ]]'
check "Sin sesion /admin/usuarios va a login" '[[ $(get anon admin/usuarios) == *"/login" ]]'
get anon login >/dev/null
check "Login sin link de registro" '! grep -qi "registrate" "$D/out.html"'

echo "== HU-02 Login"
r=$(login a admin@turnoplaner.local malaclave); check "Clave incorrecta no entra" '[[ $r == *"/login" ]]'
follow a login; check "Mensaje generico de credenciales" 'has "Usuario o contraseña incorrectos"'
r=$(login a admin@turnoplaner.local admin123); check "Admin con email entra al panel" '[[ $r == *"/inicio" ]]'
get a inicio >/dev/null; check "Panel con saludo y menu admin" 'has "Hola, Admin" && has "admin/usuarios"'
r=$(login e 30123456 enfermero123); check "Enfermera con DNI va a Mi cronograma" '[[ $r == *"/mi-cronograma" ]]'
get e mi-cronograma >/dev/null; check "Cronograma sin turnos: estado vacio de la semana y resumen sin turnos" 'has "No tiene turnos asignados en esta semana" && has "No tiene turnos programados por ahora" && ! has "0 turnos"'
check "Menu de enfermera sin Personal" '! has "admin/usuarios"'
r=$(get e admin/usuarios); check "Enfermera en URL de admin es rechazada" '[[ $r == *"/mi-cronograma" ]]'
follow e mi-cronograma; check "Alerta de permisos insuficientes" 'has "Acceso denegado"'
r=$(get a mi-cronograma); check "Admin en URL de enfermero va a su panel" '[[ $r == *"/inicio" ]]'
get e mi-cronograma >/dev/null; cp "$D/out.html" "$D/p.html"
r=$(post e logout "$D/p.html"); check "Cerrar sesion (POST) vuelve a login" '[[ $r == *"/login" ]]'
r=$(get e mi-cronograma); check "Tras cerrar sesion no hay acceso" '[[ $r == *"/login" ]]'
r=$(curl -s -o /dev/null -w "%{http_code}" $B/logout); check "Logout por GET no existe" '[[ $r == 404 ]]'

echo "== HU-01 Primer ingreso"
r=$(login n nuevo@turnoplaner.local enfermero123); check "Cuenta nueva va a activar-cuenta" '[[ $r == *"/activar-cuenta" ]]'
r=$(get n mi-cronograma); check "Bloquea cronograma antes de activar" '[[ $r == *"/activar-cuenta" ]]'
r=$(get n mi-perfil); check "Bloquea Mi perfil antes de activar" '[[ $r == *"/activar-cuenta" ]]'
get n activar-cuenta >/dev/null; cp "$D/out.html" "$D/act.html"
check "DNI y matricula en solo lectura" 'grep -q 31234567 "$D/act.html" && grep -q MP-1002 "$D/act.html" && ! grep -q "name=\"dni\"" "$D/act.html"'
post n activar-cuenta "$D/act.html" --data-urlencode "password=abc" --data-urlencode "password_confirm=abc" --data-urlencode "telefono=2920 111" --data-urlencode "domicilio=Mitre 1" >/dev/null; follow n activar-cuenta
check "Rechaza clave debil" 'has "al menos 8 caracteres"'
check "Conserva el telefono cargado" 'has "value=\"2920 111\""'
cp "$D/out.html" "$D/act.html"
post n activar-cuenta "$D/act.html" --data-urlencode "password=enfermero123" --data-urlencode "password_confirm=enfermero123" --data-urlencode "telefono=2920 111222" --data-urlencode "domicilio=Mitre 123, Viedma" >/dev/null; follow n activar-cuenta
check "Rechaza repetir la clave temporal" 'has "distinta de la contraseña temporal"'
cp "$D/out.html" "$D/act.html"
post n activar-cuenta "$D/act.html" --data-urlencode "password=Juan2026x" --data-urlencode "password_confirm=Juan2026y" --data-urlencode "telefono=2920 111222" --data-urlencode "domicilio=Mitre 123, Viedma" >/dev/null; follow n activar-cuenta
check "Rechaza confirmacion distinta" 'has "no coincide"'
cp "$D/out.html" "$D/act.html"
r=$(post n activar-cuenta "$D/act.html" --data-urlencode "password=Juan2026x" --data-urlencode "password_confirm=Juan2026x" --data-urlencode "telefono=2920 111222" --data-urlencode "domicilio=Mitre 123, Viedma")
check "Activacion exitosa va a Mi cronograma" '[[ $r == *"/mi-cronograma" ]]'
follow n mi-cronograma; check "Mensaje de bienvenida" 'has "Bienvenido/a, Juan"'
check "BD: debe_cambiar_pass=0 y domicilio guardado" '[[ $(sql "SELECT CONCAT(debe_cambiar_pass,\"|\",domicilio) FROM turnoplaner.usuarios WHERE dni=31234567") == "0|Mitre 123, Viedma" ]]'
check "BD: clave guardada con hash bcrypt" '[[ $(sql "SELECT LEFT(password_hash,4) FROM turnoplaner.usuarios WHERE dni=31234567") == "\$2y\$" ]]'
r=$(login n 31234567 Juan2026x); check "Entra con su clave nueva" '[[ $r == *"/mi-cronograma" ]]'

echo "== HU-03 Mi perfil"
get n mi-perfil >/dev/null; cp "$D/out.html" "$D/perfil.html"
check "Datos administrativos solo lectura" '! grep -q "name=\"matricula\"" "$D/perfil.html" && ! grep -q "name=\"carga_horaria_max\"" "$D/perfil.html" && grep -q MP-1002 "$D/perfil.html"'
post n mi-perfil/datos "$D/perfil.html" --data-urlencode "email=enfermera@turnoplaner.local" --data-urlencode "telefono=2920 111222" --data-urlencode "domicilio=Mitre 123, Viedma" >/dev/null; follow n mi-perfil
check "Email de otro usuario rechazado" 'has "ya pertenece a otro usuario"'
cp "$D/out.html" "$D/perfil.html"
post n mi-perfil/datos "$D/perfil.html" --data-urlencode "email=juan.perez@turnoplaner.local" --data-urlencode "telefono=2920 999888" --data-urlencode "domicilio=San Martin 45, Viedma" >/dev/null; follow n mi-perfil
check "Actualiza datos con confirmacion" 'has "se actualizaron correctamente" && has "juan.perez@turnoplaner.local"'
cp "$D/out.html" "$D/perfil.html"
post n mi-perfil/password "$D/perfil.html" --data-urlencode "password_actual=incorrecta1" --data-urlencode "password=Nueva2026" --data-urlencode "password_confirm=Nueva2026" >/dev/null; follow n mi-perfil
check "Exige la contraseña actual correcta" 'has "La contraseña actual no es correcta"'
cp "$D/out.html" "$D/perfil.html"
post n mi-perfil/password "$D/perfil.html" --data-urlencode "password_actual=Juan2026x" --data-urlencode "password=Nueva2026" --data-urlencode "password_confirm=Nueva2026" >/dev/null; follow n mi-perfil
check "Cambia la contraseña" 'has "Su contraseña se cambió correctamente"'
r=$(login n juan.perez@turnoplaner.local Nueva2026); check "Entra con email y clave nuevos" '[[ $r == *"/mi-cronograma" ]]'

echo "== HU-05 Alta (verificacion)"
login a admin@turnoplaner.local admin123 >/dev/null
get a admin/usuarios/crear >/dev/null; cp "$D/out.html" "$D/alta.html"
post a admin/usuarios/crear "$D/alta.html" --data-urlencode "nombre=Lucia" --data-urlencode "apellido=Fernandez" --data-urlencode "dni=30123456" --data-urlencode "matricula=MP-2000" --data-urlencode "email=lucia@turnoplaner.local" -d "especialidad=Oncolog%C3%ADa" --data-urlencode "carga_horaria_max=36" >/dev/null; follow a admin/usuarios/crear
check "DNI duplicado identificado" 'has "El DNI ingresado ya está registrado"'
check "Conserva lo cargado tras el error" 'has "value=\"Lucia\"" && has "selected>Oncología"'
cp "$D/out.html" "$D/alta.html"
r=$(post a admin/usuarios/crear "$D/alta.html" --data-urlencode "nombre=Lucia" --data-urlencode "apellido=Fernandez" --data-urlencode "dni=35111222" --data-urlencode "matricula=mp-2000" --data-urlencode "email=Lucia@TurnoPlaner.local" -d "especialidad=Oncolog%C3%ADa" --data-urlencode "carga_horaria_max=36")
check "Alta redirige al listado" '[[ $r == *"/admin/usuarios" ]]'
follow a admin/usuarios; cp "$D/out.html" "$D/lista.html"
check "Muestra las credenciales temporales" 'grep -q "Credenciales temporales de Fernandez, Lucia" "$D/lista.html"'
CLAVE=$(grep -o 'id="clave-temporal">[^<]*' "$D/lista.html" | sed 's/.*>//')
follow a admin/usuarios; check "Las credenciales no se repiten al recargar" '! has "Credenciales temporales"'
check "BD: ACTIVO, debe cambiar, matricula y email normalizados" '[[ $(sql "SELECT CONCAT(estado,debe_cambiar_pass,matricula,email) FROM turnoplaner.usuarios WHERE dni=35111222") == "ACTIVO1MP-2000lucia@turnoplaner.local" ]]'
r=$(login l 35111222 "$CLAVE"); check "La clave temporal mostrada funciona" '[[ $r == *"/activar-cuenta" ]]'

echo "== HU-06 Listado y busqueda"
login a admin@turnoplaner.local admin123 >/dev/null
get a "admin/usuarios?q=gomez+maria" >/dev/null; check "Busca por nombre y apellido" 'has "Gomez, Maria" && ! has "Fernandez, Lucia"'
get a "admin/usuarios?q=35111" >/dev/null; check "Busca por DNI parcial" 'has "Fernandez, Lucia"'
get a "admin/usuarios?especialidad=Onco" >/dev/null; check "Especialidad invalida se ignora" 'has "Gomez, Maria"'
get a "admin/usuarios?especialidad=Oncolog%C3%ADa" >/dev/null; check "Filtra por especialidad" 'has "Fernandez, Lucia" && ! has "Gomez, Maria"'
get a "admin/usuarios?q=zzzz" >/dev/null; check "Estado vacio con busqueda" 'has "No hay resultados para esa búsqueda"'
get a "admin/usuarios?q=%3Cscript%3Ealert(1)%3C/script%3E" >/dev/null; check "Busqueda escapada (sin XSS)" '! has "<script>alert(1)"'
get a admin/usuarios >/dev/null
check "Muestra todos los datos que pide la HU (nombre, DNI, matricula, especialidad, carga, rol, estado)" 'has "Nombre y apellido" && has "DNI <span" && has "· Matrícula" && has "Especialidad" && has "Carga máx." && has "· Rol" && has ">Estado<" && has "MP-1001" && has "Enfermero/a"'

echo "== HU-07 Edicion"
ID=$(sql "SELECT id FROM turnoplaner.usuarios WHERE dni=35111222")
HASH0=$(sql "SELECT password_hash FROM turnoplaner.usuarios WHERE id=$ID")
get a admin/usuarios/$ID/editar >/dev/null; cp "$D/out.html" "$D/ed.html"
check "Formulario precompletado" 'grep -q "value=\"Lucia\"" "$D/ed.html" && grep -q "value=\"36\"" "$D/ed.html"'
post a admin/usuarios/$ID/editar "$D/ed.html" --data-urlencode "nombre=Lucia" --data-urlencode "apellido=Fernandez" --data-urlencode "dni=35111222" --data-urlencode "matricula=MP-1001" --data-urlencode "email=lucia@turnoplaner.local" -d "especialidad=Oncolog%C3%ADa" --data-urlencode "carga_horaria_max=36" >/dev/null; follow a admin/usuarios/$ID/editar
check "Matricula de otro profesional rechazada" 'has "matrícula ingresada ya está registrada"'
cp "$D/out.html" "$D/ed.html"
post a admin/usuarios/$ID/editar "$D/ed.html" -d "nombre=Luc%C3%ADa" -d "apellido=Fern%C3%A1ndez" --data-urlencode "dni=35111222" --data-urlencode "matricula=MP-2000" --data-urlencode "email=lucia@turnoplaner.local" --data-urlencode "telefono=2920 333444" --data-urlencode "especialidad=Salud Mental" --data-urlencode "carga_horaria_max=40" >/dev/null
follow a admin/usuarios; check "Edicion guardada con aviso" 'has "Legajo de Fernández, Lucía actualizado"'
check "BD: cambios, updated_at y clave intacta" '[[ $(sql "SELECT CONCAT(especialidad,carga_horaria_max,(updated_at IS NOT NULL),(password_hash=\"$HASH0\")) FROM turnoplaner.usuarios WHERE id=$ID") == "Salud Mental4011" ]]'
r=$(get a admin/usuarios/1/editar); check "Un admin no se edita desde Personal (404)" '[[ $r == 404* ]]'

echo "== HU-04 Restablecer credenciales"
get a admin/usuarios >/dev/null; cp "$D/out.html" "$D/lista.html"
login m2 enfermera@turnoplaner.local enfermero123 >/dev/null   # Maria tiene una sesion abierta
post a admin/usuarios/2/restablecer "$D/lista.html" >/dev/null; follow a admin/usuarios; cp "$D/out.html" "$D/lista.html"
r=$(get m2 mi-cronograma); check "Restablecer cierra las sesiones abiertas de esa cuenta" '[[ $r == *"/login" ]]'
follow m2 login; check "Avisa que la contraseña cambio" 'has "Su contraseña cambió"'
check "Muestra la clave temporal nueva" 'grep -q "Credenciales temporales de Gomez, Maria" "$D/lista.html"'
CLAVE2=$(grep -o 'id="clave-temporal">[^<]*' "$D/lista.html" | sed 's/.*>//')
r=$(login m enfermera@turnoplaner.local enfermero123); check "La clave vieja deja de funcionar" '[[ $r == *"/login" ]]'
r=$(login m enfermera@turnoplaner.local "$CLAVE2"); check "El proximo ingreso pide cambiarla" '[[ $r == *"/activar-cuenta" ]]'

echo "== HU-08 Baja con sesion abierta"
login n juan.perez@turnoplaner.local Nueva2026 >/dev/null
login a admin@turnoplaner.local admin123 >/dev/null; get a admin/usuarios >/dev/null; cp "$D/out.html" "$D/lista.html"
JID=$(sql "SELECT id FROM turnoplaner.usuarios WHERE dni=31234567")
post a admin/usuarios/baja/$JID "$D/lista.html" >/dev/null
r=$(get n mi-cronograma); check "La sesion abierta de un dado de baja se corta" '[[ $r == *"/login" ]]'
follow n login; check "Le explica que la cuenta fue dada de baja" 'has "Cuenta inactiva o dada de baja"'
login n juan.perez@turnoplaner.local Nueva2026 >/dev/null; follow n login; check "Login de una baja avisa cuenta inactiva" 'has "Cuenta inactiva o dada de baja"'
get a "admin/usuarios?estado=BAJA" >/dev/null; cp "$D/out.html" "$D/lista.html"; check "Filtro Dados de baja lo muestra" 'grep -q "Perez, Juan" "$D/lista.html"'
post a admin/usuarios/$JID/reactivar "$D/lista.html" >/dev/null
r=$(login n juan.perez@turnoplaner.local Nueva2026); check "Reactivado vuelve a entrar" '[[ $r == *"/mi-cronograma" ]]'

echo "== Hallazgos de las revisiones QA y UX/UI"
r=$(login d "20.000.000" admin123); check "Login con DNI escrito con puntos" '[[ $r == *"/inicio" ]]'
get d inicio >/dev/null; cp "$D/out.html" "$D/p.html"; post d logout "$D/p.html" >/dev/null; follow d login
check "Muestra 'Sesion cerrada correctamente'" 'has "Sesión cerrada correctamente"'
login a admin@turnoplaner.local admin123 >/dev/null
r=$(get a admin/usuarios/99999999999999999999/editar); check "ID enorme en la URL da 404 (no error 500)" '[[ $r == 404* ]]'
r=$(curl -s -D - -o /dev/null $B/login | grep -i "^x-frame-options"); check "Cabecera X-Frame-Options presente" '[[ -n $r ]]'
get a admin/usuarios/crear >/dev/null; cp "$D/out.html" "$D/alta.html"
post a admin/usuarios/crear "$D/alta.html" -d "nombre=Ana&apellido=O%27Brien+%26+Hijos&dni=30123456&matricula=MP-3000&email=ana%40turnoplaner.local&especialidad=Salud+Mental&carga_horaria_max=30" >/dev/null; follow a admin/usuarios/crear
check "Tras un error, el apellido con comilla y & se escapa una sola vez" 'has "value=\"O&#039;Brien &amp; Hijos\"" && ! has "&amp;#039;"'
cp "$D/out.html" "$D/alta.html"
post a admin/usuarios/crear "$D/alta.html" -d "nombre=Ana&apellido=O%27Brien+%26+Hijos&dni=36000111&matricula=MP-3000&email=ana%40turnoplaner.local&especialidad=Salud+Mental&carga_horaria_max=30" >/dev/null
check "BD guarda O'Brien & Hijos sin entidades HTML" '[[ $(sql "SELECT apellido FROM turnoplaner.usuarios WHERE dni=36000111") == "O'"'"'Brien & Hijos" ]]'
follow a admin/usuarios; CLAVE3=$(grep -o 'id="clave-temporal">[^<]*' "$D/out.html" | sed 's/.*>//')
get a "admin/usuarios?estado=PENDIENTE" >/dev/null; check "Filtro Pendientes de activacion" 'has "Brien" && has "Gomez, Maria" && ! has "Perez, Juan"'
login o 36000111 "$CLAVE3" >/dev/null; get o activar-cuenta >/dev/null; cp "$D/out.html" "$D/act.html"
post o activar-cuenta "$D/act.html" --data-urlencode "password=ClaveSecreta99" --data-urlencode "password_confirm=OtraDistinta1" --data-urlencode "telefono=------" --data-urlencode "domicilio=Belgrano 50, Viedma" >/dev/null; follow o activar-cuenta
check "Telefono sin numeros es rechazado" 'has "al menos 6 números"'
check "Las contraseñas no quedan guardadas en los archivos de sesion" '! grep -rqs "ClaveSecreta99" "$RAIZ/writable/session/"'

echo "== HU-09 / HU-10 Sectores"
login a admin@turnoplaner.local admin123 >/dev/null
get a admin/sectores >/dev/null; check "Listado con los 4 sectores y UTI como area critica" 'has ">UTI<" && has "Neonatolog" && has "Área crítica" && has "3 enfermeros/as por turno"'
get a admin/sectores/crear >/dev/null; cp "$D/out.html" "$D/s.html"
post a admin/sectores/crear "$D/s.html" -d "nombre=uti&dotacion_minima=2&es_critico=1" >/dev/null; follow a admin/sectores/crear
check "Nombre repetido rechazado (sin importar mayusculas)" 'has "Ya existe un sector con ese nombre"'
cp "$D/out.html" "$D/s.html"
post a admin/sectores/crear "$D/s.html" -d "nombre=Pediatr%C3%ADa&dotacion_minima=0&es_critico=0" >/dev/null; follow a admin/sectores/crear
check "Dotacion minima 0 rechazada" 'has "debe ser un número entero mayor a cero"'
cp "$D/out.html" "$D/s.html"
r=$(post a admin/sectores/crear "$D/s.html" -d "nombre=Pediatr%C3%ADa&descripcion=Internaci%C3%B3n+pedi%C3%A1trica&dotacion_minima=2&es_critico=0")
check "Alta de sector redirige al listado" '[[ $r == *"/admin/sectores" ]]'
follow a admin/sectores; check "Sector nuevo listado con confirmacion" 'has "Sector «Pediatría» creado" && has "2 enfermeros/as por turno"'
PED=$(sql "SELECT id FROM turnoplaner.sectores WHERE nombre LIKE 'Pediatr%'")
get a admin/sectores/$PED/editar >/dev/null; cp "$D/out.html" "$D/s.html"
post a admin/sectores/$PED/editar "$D/s.html" -d "nombre=Pediatr%C3%ADa&descripcion=Internaci%C3%B3n+pedi%C3%A1trica&dotacion_minima=2&es_critico=0" >/dev/null; follow a admin/sectores
check "Editar sin cambios avisa como info" 'has "No había cambios para guardar en «Pediatría»"'
get a admin/sectores/$PED/editar >/dev/null; cp "$D/out.html" "$D/s.html"
post a admin/sectores/$PED/editar "$D/s.html" -d "nombre=Pediatr%C3%ADa&descripcion=&dotacion_minima=4&es_critico=1" >/dev/null
check "BD: edicion aplicada" '[[ $(sql "SELECT CONCAT(dotacion_minima,es_critico,IFNULL(descripcion,\"null\")) FROM turnoplaner.sectores WHERE id=$PED") == "41null" ]]'

echo "== HU-11 / HU-12 Tipos de turno"
get a admin/tipos-turno >/dev/null; check "Listado con Noche 22:00 a 06:00 (+1 dia) de 8 h" 'texto | grep -q "22:00 →a 06:00 (+1 día), termina al día siguiente" && has "8 h" && has "12 h"'
get a admin/tipos-turno/crear >/dev/null; cp "$D/out.html" "$D/t.html"
post a admin/tipos-turno/crear "$D/t.html" -d "nombre=Partido&hora_inicio=10:00&hora_fin=10:00&color=%23307f6d" >/dev/null; follow a admin/tipos-turno/crear
check "Inicio igual a fin rechazado" 'has "no puede ser igual a la de inicio"'
cp "$D/out.html" "$D/t.html"
post a admin/tipos-turno/crear "$D/t.html" -d "nombre=Partido&hora_inicio=25:00&hora_fin=10:00&color=%23307f6d" >/dev/null; follow a admin/tipos-turno/crear
check "Hora invalida rechazada" 'has "formato 24 h"'
cp "$D/out.html" "$D/t.html"
post a admin/tipos-turno/crear "$D/t.html" -d "nombre=noche&hora_inicio=21:00&hora_fin=05:00&color=%23307f6d" >/dev/null; follow a admin/tipos-turno/crear
check "Nombre repetido rechazado" 'has "Ya existe un tipo de turno con ese nombre"'
cp "$D/out.html" "$D/t.html"
r=$(post a admin/tipos-turno/crear "$D/t.html" -d "nombre=Trasnoche&hora_inicio=23:30&hora_fin=07:00&color=%231f6f8b")
check "Alta de turno que cruza la medianoche" '[[ $r == *"/admin/tipos-turno" ]]'
check "BD: duracion calculada 7 h 30 min (450 min)" '[[ $(sql "SELECT duracion_minutos FROM turnoplaner.tipos_turno WHERE nombre=\"Trasnoche\"") == "450" ]]'
follow a admin/tipos-turno; check "Se muestra 23:30 a 07:00 (+1 dia) y 7 h 30 min" 'texto | grep -q "23:30 →a 07:00" && has "7 h 30 min"'
TRAS=$(sql "SELECT id FROM turnoplaner.tipos_turno WHERE nombre='Trasnoche'")
get a admin/tipos-turno/$TRAS/editar >/dev/null; cp "$D/out.html" "$D/t.html"
post a admin/tipos-turno/$TRAS/editar "$D/t.html" -d "nombre=Trasnoche&hora_inicio=23:00&hora_fin=07:00&color=%231f6f8b" >/dev/null
check "Edicion recalcula la duracion (8 h)" '[[ $(sql "SELECT duracion_minutos FROM turnoplaner.tipos_turno WHERE id=$TRAS") == "480" ]]'
get a admin/tipos-turno >/dev/null; cp "$D/out.html" "$D/t.html"
post a admin/tipos-turno/$TRAS/baja "$D/t.html" >/dev/null; follow a admin/tipos-turno
check "Baja de un turno sin asignaciones" 'has "«Trasnoche» fue dado de baja"'
check "Ya no aparece entre los activos" '! texto | grep -q "Trasnoche 23:00"'
get a "admin/tipos-turno?estado=BAJA" >/dev/null; cp "$D/out.html" "$D/t.html"; check "Aparece en Dados de baja" 'has "Trasnoche"'
post a admin/tipos-turno/$TRAS/reactivar "$D/t.html" >/dev/null; follow a admin/tipos-turno; check "Reactivacion" 'has "«Trasnoche» fue reactivado"'

echo "== HU-13 Asignacion de turnos"
LUN=$(date -d "next monday" +%F); MAR=$(date -d "$LUN +1 day" +%F); MIE=$(date -d "$LUN +2 day" +%F); JUE=$(date -d "$LUN +3 day" +%F); AYER=$(date -d "yesterday" +%F)
MARIA=$(sql "SELECT id FROM turnoplaner.usuarios WHERE dni=30123456"); JUAN=$(sql "SELECT id FROM turnoplaner.usuarios WHERE dni=31234567")
asignar(){ get a "admin/asignaciones/crear" >/dev/null; cp "$D/out.html" "$D/as.html"; post a admin/asignaciones/crear "$D/as.html" -d "usuario_id=$1&sector_id=$2&tipo_turno_id=$3&fecha=$4"; }
r=$(asignar $MARIA 1 1 $LUN); check "Asigna Maria UTI Mañana el lunes y vuelve al planificador" '[[ $r == *"/admin/planificador?sector=1&semana=$LUN" ]]'
check "BD: inicio y fin calculados" '[[ $(sql "SELECT CONCAT(inicio,\"|\",fin) FROM turnoplaner.asignaciones WHERE usuario_id=$MARIA AND fecha=\"$LUN\"") == "$LUN 06:00:00|$LUN 14:00:00" ]]'
r=$(asignar $MARIA 1 3 $LUN); check "Noche del lunes (22 a 06) no choca con la mañana" '[[ $r == *"/admin/planificador"* ]]'
check "BD: la noche termina el martes a las 06:00" '[[ $(sql "SELECT fin FROM turnoplaner.asignaciones WHERE usuario_id=$MARIA AND tipo_turno_id=3") == "$MAR 06:00:00" ]]'
r=$(asignar $MARIA 1 1 $MAR); check "Mañana del martes (06:00) tras la noche (hasta 06:00) no es superposicion" '[[ $r == *"/admin/planificador"* ]]'
asignar $MARIA 2 4 $LUN >/dev/null; follow a admin/asignaciones/crear
check "Guardia especial del lunes (08 a 20) se superpone y se rechaza diciendo con que turno" 'has "ya tiene un turno de Mañana en UTI que se superpone"'
check "Conserva lo elegido tras el error" 'has "value=\"$LUN\""'
r=$(asignar $MARIA 1 2 $MIE); check "Tarde del miercoles: 32 h de 36" '[[ $r == *"/admin/planificador"* ]]'
asignar $MARIA 1 2 $JUE >/dev/null; follow a admin/asignaciones/crear
check "Tarde del jueves superaria 36 h y se rechaza explicando la cuenta" 'has "Supera la carga horaria" && has "ya tiene 32 h asignadas" && has "llegaría a 40 h"'
asignar $MARIA 1 1 $AYER >/dev/null; follow a admin/asignaciones/crear
check "Fecha pasada rechazada" 'has "Solo se pueden asignar turnos de hoy en adelante"'
get a admin/sectores >/dev/null; cp "$D/out.html" "$D/s.html"; post a admin/sectores/$PED/baja "$D/s.html" >/dev/null
asignar $JUAN $PED 1 $LUN >/dev/null; follow a admin/asignaciones/crear
check "Sector dado de baja no se puede asignar" 'has "El sector elegido no está activo"'
get a admin/asignaciones/crear >/dev/null; check "El sector de baja no aparece en la lista" '! has "Pediatría"'

echo "== HU-14 Cuadricula semanal"
get a "admin/planificador?sector=1&semana=$LUN" >/dev/null; cp "$D/out.html" "$D/pl.html"
check "Muestra la semana del lunes al domingo" 'grep -q "Semana del $(date -d $LUN +%d/%m/%Y) al $(date -d "$LUN +6 day" +%d/%m/%Y)" "$D/pl.html"'
check "Muestra a Maria en la cuadricula" 'grep -q "Gomez, Maria" "$D/pl.html"'
check "Estado de cobertura contra la dotacion minima de UTI (3)" 'grep -q "Faltan 2 · 1/3" "$D/pl.html" && grep -q "Sin cubrir 0/3" "$D/pl.html"'
check "Boton Asignar precarga sector, turno y fecha" 'grep -q "admin/asignaciones/crear?sector=1&amp;tipo=1&amp;fecha=$LUN" "$D/pl.html"'
r=$(get a "admin/planificador?sector=1&semana=no-es-fecha"); check "Semana invalida no rompe (usa la actual)" '[[ $r == 200* ]]'
asignar $JUAN 1 1 $LUN >/dev/null; asignar $(sql "SELECT id FROM turnoplaner.usuarios WHERE dni=35111222") 1 1 $LUN >/dev/null
get a "admin/planificador?sector=1&semana=$LUN" >/dev/null; check "Con 3 asignados la mañana del lunes queda Cubierto 3/3" 'has "Cubierto 3/3"'
login z juan.perez@turnoplaner.local Nueva2026 >/dev/null
r=$(get z admin/planificador); check "Un enfermero no entra al planificador" '[[ $r == *"/mi-cronograma" ]]'
r=$(get z admin/asignaciones/crear); check "Un enfermero no puede asignar turnos" '[[ $r == *"/mi-cronograma" ]]'

echo "== HU-13 Reasignar y eliminar / HU-12 integridad"
AMIE=$(sql "SELECT id FROM turnoplaner.asignaciones WHERE usuario_id=$MARIA AND fecha=\"$MIE\"")
get a admin/asignaciones/$AMIE/editar >/dev/null; cp "$D/out.html" "$D/ed.html"
check "Formulario de reasignar precargado" 'grep -q "value=\"$MIE\"" "$D/ed.html" && grep -q "Eliminar turno" "$D/ed.html"'
post a admin/asignaciones/$AMIE/editar "$D/ed.html" -d "usuario_id=$JUAN&sector_id=1&tipo_turno_id=2&fecha=$MIE" >/dev/null
check "Reasignado a Juan" '[[ $(sql "SELECT usuario_id FROM turnoplaner.asignaciones WHERE id=$AMIE") == "$JUAN" ]]'
r=$(asignar $MARIA 1 2 $JUE); check "Al liberar horas, Maria ya puede tomar el jueves" '[[ $r == *"/admin/planificador"* ]]'
get a admin/tipos-turno >/dev/null; cp "$D/out.html" "$D/t.html"; post a admin/tipos-turno/1/baja "$D/t.html" >/dev/null; follow a admin/tipos-turno
check "No deja dar de baja un turno con asignaciones futuras (HU-12)" 'has "tiene turnos asignados desde hoy en adelante"'
get a admin/sectores >/dev/null; cp "$D/out.html" "$D/s.html"; post a admin/sectores/1/baja "$D/s.html" >/dev/null; follow a admin/sectores
check "No deja dar de baja un sector con asignaciones futuras" 'has "No se puede dar de baja «UTI»"'
get a admin/asignaciones/$AMIE/editar >/dev/null; cp "$D/out.html" "$D/ed.html"
post a admin/asignaciones/$AMIE/eliminar "$D/ed.html" >/dev/null; follow a "admin/planificador?sector=1&semana=$LUN"
check "Eliminar turno futuro con confirmacion" 'has "Turno eliminado del"'
check "BD: eliminado con baja logica (deleted_at)" '[[ $(sql "SELECT deleted_at IS NOT NULL FROM turnoplaner.asignaciones WHERE id=$AMIE") == "1" ]]'

echo "== Hallazgos QA / UX sprint 2 y 3"
LUN_SIN_CEROS=$(date -d "$LUN" +%Y-%-m-%-d); MANANA=$(date -d "tomorrow" +%F)
asignar $MARIA 1 1 "2020-1-5" >/dev/null; follow a admin/asignaciones/crear
check "Fecha pasada sin ceros (2020-1-5) rechazada" 'has "Ingrese una fecha válida"'
asignar $MARIA 1 1 "$(date -d "+400 days" +%F)" >/dev/null; follow a admin/asignaciones/crear
check "Fecha a mas de un año rechazada" 'has "hasta un año hacia adelante"'
get a "admin/asignaciones/crear?sector=1&tipo=1&fecha=$LUN" >/dev/null
check "La lista muestra las horas de la semana de cada enfermero/a" 'has "de 36 h esta semana"'
check "Quien ya tiene un turno superpuesto aparece no disponible" 'has "no disponible: ya tiene un turno que se superpone"'
get a admin/tipos-turno/1/editar >/dev/null; cp "$D/out.html" "$D/t.html"
post a admin/tipos-turno/1/editar "$D/t.html" -d "nombre=Ma%C3%B1ana&hora_inicio=07:00&hora_fin=15:00&color=%23c98a00" >/dev/null; follow a admin/tipos-turno/1/editar
check "No deja cambiar el horario de un turno con asignaciones futuras" 'has "No se puede cambiar el horario de «Mañana»"'
cp "$D/out.html" "$D/t.html"
post a admin/tipos-turno/1/editar "$D/t.html" -d "nombre=Ma%C3%B1ana&hora_inicio=06:00&hora_fin=14:00&color=%23ff0000" >/dev/null; follow a admin/tipos-turno/1/editar
check "Color fuera de la paleta rechazado" 'has "Elija uno de los colores de la lista"'
get a admin/usuarios >/dev/null; cp "$D/out.html" "$D/u.html"
post a admin/usuarios/baja/$MARIA "$D/u.html" >/dev/null; follow a admin/usuarios
check "No deja dar de baja a un enfermero/a con turnos futuros" 'has "No se puede dar de baja a Gomez, Maria" && has "Planificador"'
check "BD: sigue activa" '[[ $(sql "SELECT estado FROM turnoplaner.usuarios WHERE id=$MARIA") == "ACTIVO" ]]'
get a "admin/planificador?sector=1&semana=$LUN" >/dev/null
check "Cuadricula accesible: el nombre dice que turno y dia reasigna" 'has "reasignar su turno Mañana del lunes" && has "Quitar a Gomez, Maria del turno Mañana"'
check "Cuadricula accesible: la cobertura se lee completa" 'has "de 3 enfermeros mínimos" || has "de 3 asignados"'
check "Sin opacidad en los dias pasados" '! grep -q "opacity: 0.75" "$RAIZ/public/css/app.css"'

echo "== HU-14 Modal AJAX y resumen de horas"
ajax(){ local url=$1 page=$2; shift 2; curl -s -b "$D/a" -c "$D/a" -o "$D/out.html" -w "%{http_code}" -H "X-Requested-With: XMLHttpRequest" -H "Accept: application/json" --data-urlencode "csrf_test_name=$(tok "$page")" "$@" "$B/$url"; }
get a "admin/planificador?sector=2&semana=$LUN" >/dev/null; cp "$D/out.html" "$D/pl.html"
check "La cuadricula trae el modal de asignar y el de quitar" 'grep -q "id=\"modalAsignar\"" "$D/pl.html" && grep -q "id=\"modalQuitar\"" "$D/pl.html" && grep -q "data-modal-asignar" "$D/pl.html"'
check "Resumen de horas de la semana por enfermero/a" 'grep -q "Horas de la semana por enfermero/a" "$D/pl.html" && grep -q "de 36 h" "$D/pl.html"'
r=$(ajax admin/asignaciones/crear "$D/pl.html" -d "usuario_id=$JUAN&sector_id=2&tipo_turno_id=4&fecha=$JUE")
check "AJAX: asigna desde el modal y responde JSON ok" '[[ $r == 200 ]] && has "\"ok\": true"'
get a "admin/planificador?sector=2&semana=$LUN" >/dev/null; cp "$D/out.html" "$D/pl.html"
check "Tras recargar, la cuadricula muestra el mensaje y al enfermero en Guardia" 'grep -q "Turno asignado: Perez, Juan · Guardia" "$D/pl.html" && grep -q "data-quitar" "$D/pl.html"'
r=$(ajax admin/asignaciones/crear "$D/pl.html" -d "usuario_id=$JUAN&sector_id=2&tipo_turno_id=1&fecha=$JUE")
check "AJAX: superposicion devuelve 422 con el error en JSON (validaciones de HU-13)" '[[ $r == 422 ]] && has "se superpone" && has "\"csrf\""'
NUEVA=$(sql "SELECT id FROM turnoplaner.asignaciones WHERE usuario_id=$JUAN AND sector_id=2 AND fecha=\"$JUE\" AND deleted_at IS NULL")
r=$(ajax admin/asignaciones/$NUEVA/eliminar "$D/pl.html")
check "AJAX: con el token del error anterior ya usado, el POST se rechaza (CSRF regenerado)" '[[ $r == 403 ]]'
get a "admin/planificador?sector=2&semana=$LUN" >/dev/null; cp "$D/out.html" "$D/pl.html"
r=$(ajax admin/asignaciones/$NUEVA/eliminar "$D/pl.html")
check "AJAX: quitar desde el modal" '[[ $r == 200 ]] && has "\"ok\": true" && [[ $(sql "SELECT deleted_at IS NOT NULL FROM turnoplaner.asignaciones WHERE id=$NUEVA") == "1" ]]'

echo "== HU-17 Mi cronograma"
login z juan.perez@turnoplaner.local Nueva2026 >/dev/null
get z "mi-cronograma?vista=semana&fecha=$LUN" >/dev/null
check "Semana: muestra su turno con fecha, sector, turno y horario" 'texto | grep -q "lunes $(date -d $LUN +%d/%m/%Y)" && has "UTI" && has ">Mañana<" && texto | grep -q "06:00 →a 14:00"'
check "Semana: estado del turno (Programado)" 'has "estado-turno--programado" && has ">Programado<"'
check "Semana: los dias sin turno dicen Sin turno asignado" 'has "Sin turno asignado" && ! has "Franco"'
check "Semana: total del periodo" 'has "1 turno en este período" && has "8 h en total"'
check "Privacidad: no ve los turnos de otra enfermera" '! has "Gomez" && ! has ">Noche<"'
check "Resumen Hoy/Mañana arriba (con el proximo turno si no es mañana)" 'has "titulo-resumen" && { has "Próximo turno:" || [[ $LUN == $(date -d tomorrow +%F) ]]; }'
check "Lector de pantalla: una frase completa por turno en la agenda" 'has "Mañana en UTI" && has "de 06:00 a 14:00, programado."'
get z "mi-cronograma?vista=mes&fecha=$LUN" >/dev/null
check "Mes: titulo del mes y su turno" 'has "$(date -d $LUN +%Y)" && has "1 turno en este período" && has ">Mañana<"'
check "Mes: navegacion Anterior, Este mes y Siguiente" 'has "Anterior" && has "Este mes" && has "Siguiente"'
get z "mi-cronograma?vista=semana&fecha=$LUN&usuario_id=$MARIA" >/dev/null
check "Privacidad: un usuario_id en la URL no cambia de quien son los turnos" '! has "Gomez" && has "1 turno en este período"'
r=$(get z "mi-cronograma?vista=%3Cscript%3E&fecha=no-fecha"); check "Parametros invalidos no rompen (vista semana por defecto)" '[[ $r == 200* ]] && has "Semana del"'
get z "mi-cronograma?vista=mes&fecha=2020-01-15" >/dev/null
check "Mes sin turnos: estado vacio con link al mes actual" 'has "No tiene turnos asignados en enero 2020" && has "Ir al mes actual"'

echo "== HU-17 Bordes (revision QA)"
r=$(get z "mi-cronograma?vista[]=mes&fecha[]=x"); check "Parametros en forma de arreglo no dan error 500" '[[ $r == 200* ]] && has "Semana del"'
r=$(get a "admin/usuarios?estado[]=BAJA&q[]=x"); check "Arreglos en filtros del admin tampoco rompen" '[[ $r == 200* ]]'
r=$(get a "admin/planificador?sector[]=1&semana[]=x"); check "Arreglos en el planificador tampoco rompen" '[[ $r == 200* ]]'
get z "mi-cronograma?vista=semana&fecha=2026-12-31" >/dev/null; check "Semana con cambio de año" 'has "Semana del 28/12/2026 al 03/01/2027"'
get z "mi-cronograma?vista=semana&fecha=2026-02-30" >/dev/null; check "Fecha inexistente cae a la semana actual" 'has "Semana del $(date -d "$(date +%F) -$(( $(date +%u) - 1 )) day" +%d/%m/%Y)"'
get z "mi-cronograma?vista=semana&fecha=9999-12-31" >/dev/null; check "Año fuera de rango cae a la semana actual" 'has "Semana del $(date -d "$(date +%F) -$(( $(date +%u) - 1 )) day" +%d/%m/%Y)" && ! texto | grep -q "/9999"'
get z "mi-cronograma?vista=mes" >/dev/null; check "Desde Este mes, el boton Semana va a la semana de hoy" 'has "vista=semana&amp;fecha=$(date +%F)"'
get a "admin/asignaciones/crear" >/dev/null; cp "$D/out.html" "$D/as.html"; post a admin/asignaciones/crear "$D/as.html" -d "usuario_id=$JUAN&sector_id=1&tipo_turno_id=3&fecha=$MAR" >/dev/null
get z "mi-cronograma?vista=semana&fecha=$LUN" >/dev/null; check "Turno Noche: muestra el dia de salida" 'texto | grep -q "22:00 →a 06:00 del mié $(date -d $MIE +%d/%m)"'
check "Al otro dia: nota de salida de la Noche" 'has "Sale de Noche a las 06:00"'

echo "== Limite de intentos de login"
rm -f "$D/t"; get t login >/dev/null; cp "$D/out.html" "$D/f.html"
for i in 1 2 3 4 5 6; do post t login "$D/f.html" --data-urlencode "identificador=x@y.z" --data-urlencode "password=x" >/dev/null; follow t login; cp "$D/out.html" "$D/f.html"; done
check "Bloquea tras 5 intentos por minuto" 'grep -q "Demasiados intentos" "$D/f.html"'

echo; echo "RESULTADO: $PASS OK, $FAIL fallas"; rm -rf "$D"
exit $(( FAIL > 0 ))
