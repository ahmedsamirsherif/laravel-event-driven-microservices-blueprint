#!/bin/bash
# Full lifecycle seed for HR and Hub employee flows.
# Usage: bash seed-employees.sh [USA_COUNT] [DEU_COUNT]
# Env: PARALLEL, VERBOSE, MAX_RETRIES, RETRY_WAIT, UPDATE_PCT, DELETE_PCT

set -euo pipefail

API="http://localhost:8001/api/v1/employees"
HUB_API="http://localhost:8002/api/v1"
TOTAL_USA=${1:-1500}
TOTAL_DEU=${2:-1500}
TOTAL=$((TOTAL_USA + TOTAL_DEU))

PARALLEL=${PARALLEL:-100}
MAX_RETRIES=${MAX_RETRIES:-3}
RETRY_WAIT=${RETRY_WAIT:-1}
PROPAGATION_WAIT=${PROPAGATION_WAIT:-3}
LOG_FILE=${LOG_FILE:-"seed-$(date +%Y%m%d_%H%M%S).log"}
VERBOSE=${VERBOSE:-0}

UPDATE_PCT=${UPDATE_PCT:-30}
DELETE_PCT=${DELETE_PCT:-10}

TEMP_DIR=$(mktemp -d)
CREATED_IDS_FILE="$TEMP_DIR/created_ids"
CREATE_OK_FILE="$TEMP_DIR/create_ok"
CREATE_FAIL_FILE="$TEMP_DIR/create_fail"
UPDATE_OK_FILE="$TEMP_DIR/update_ok"
UPDATE_FAIL_FILE="$TEMP_DIR/update_fail"
DELETE_OK_FILE="$TEMP_DIR/delete_ok"
DELETE_FAIL_FILE="$TEMP_DIR/delete_fail"
trap 'rm -rf "$TEMP_DIR"' EXIT
touch \
  "$CREATED_IDS_FILE" \
  "$CREATE_OK_FILE" \
  "$CREATE_FAIL_FILE" \
  "$UPDATE_OK_FILE" \
  "$UPDATE_FAIL_FILE" \
  "$DELETE_OK_FILE" \
  "$DELETE_FAIL_FILE"

US_FIRST=("James" "Mary" "Robert" "Patricia" "John" "Jennifer" "Michael" "Linda" "David" "Elizabeth" "William" "Barbara" "Richard" "Susan" "Joseph" "Jessica" "Thomas" "Sarah" "Christopher" "Karen" "Daniel" "Lisa" "Matthew" "Nancy" "Anthony" "Betty" "Mark" "Margaret" "Steven" "Sandra" "Paul" "Ashley" "Andrew" "Dorothy" "Joshua" "Kimberly" "Kenneth" "Emily" "Kevin" "Donna" "Brian" "Michelle" "George" "Carol" "Timothy" "Amanda" "Ronald" "Melissa" "Edward" "Deborah")
US_LAST=("Smith" "Johnson" "Williams" "Brown" "Jones" "Garcia" "Miller" "Davis" "Rodriguez" "Martinez" "Hernandez" "Lopez" "Gonzalez" "Wilson" "Anderson" "Thomas" "Taylor" "Moore" "Jackson" "Martin" "Lee" "Perez" "Thompson" "White" "Harris" "Sanchez" "Clark" "Ramirez" "Lewis" "Robinson" "Walker" "Young" "Allen" "King" "Wright" "Scott" "Torres" "Nguyen" "Hill" "Flores" "Green" "Adams" "Nelson" "Baker" "Hall" "Rivera" "Campbell" "Mitchell" "Carter" "Roberts")
DE_FIRST=("Hans" "Klaus" "Jurgen" "Wolfgang" "Dieter" "Helmut" "Manfred" "Gerhard" "Werner" "Peter" "Stefan" "Andreas" "Markus" "Bernd" "Uwe" "Ralf" "Frank" "Martin" "Heiko" "Lars" "Anna" "Petra" "Sabine" "Monika" "Claudia" "Karin" "Ursula" "Brigitte" "Ingrid" "Helga" "Renate" "Elke" "Maria" "Gabriele" "Erika" "Gisela" "Andrea" "Birgit" "Susanne" "Christine" "Tobias" "Florian" "Lukas" "Felix" "Maximilian" "Sophie" "Lena" "Hannah" "Julia" "Katharina")
DE_LAST=("Mueller" "Schmidt" "Schneider" "Fischer" "Weber" "Meyer" "Wagner" "Becker" "Schulz" "Hoffmann" "Schafer" "Koch" "Bauer" "Richter" "Klein" "Wolf" "Schroeder" "Neumann" "Schwarz" "Zimmermann" "Braun" "Krueger" "Hofmann" "Hartmann" "Lange" "Schmitt" "Werner" "Schmitz" "Krause" "Meier" "Lehmann" "Schmid" "Schulze" "Maier" "Koehler" "Herrmann" "Koenig" "Walter" "Mayer" "Huber" "Kaiser" "Fuchs" "Peters" "Lang" "Scholz" "Moeller" "Weiss" "Jung" "Hahn" "Vogel")

STREETS=("Main St" "Oak Ave" "Elm St" "Maple Dr" "Pine Rd" "Cedar Ln" "Broadway" "Park Ave" "Lake Dr" "Ridge Rd" "Valley Blvd" "Hill St" "River Rd" "Forest Ave" "Sunset Blvd" "Church St" "Washington Ave" "Lincoln Rd" "Jefferson Blvd" "Franklin Dr")
US_CITIES=("New York, NY 10001" "Los Angeles, CA 90001" "Chicago, IL 60601" "Houston, TX 77001" "Phoenix, AZ 85001" "Philadelphia, PA 19101" "San Antonio, TX 78201" "San Diego, CA 92101" "Dallas, TX 75201" "Austin, TX 78701" "Denver, CO 80201" "Seattle, WA 98101" "Boston, MA 02101" "Portland, OR 97201" "Miami, FL 33101")
GOALS=(
  "Complete onboarding and integrate with engineering team"
  "Achieve full productivity within 90 days"
  "Master internal tools and documentation"
  "Lead first project milestone by end of quarter"
  "Build cross-team relationships and knowledge sharing"
  "Complete compliance and security training"
  "Deliver Q1 objectives ahead of schedule"
  "Establish workflow with assigned mentor"
  "Deploy first feature to production with full coverage"
  "Document onboarding experience for future hires"
)
UPDATED_GOALS=(
  "Revised: lead team initiative in Q2"
  "Revised: obtain advanced product certification"
  "Revised: mentor two junior engineers by end of year"
  "Revised: migrate legacy module to new architecture"
  "Revised: complete security audit and remediation"
)
DOC_FIELDS=(
  doc_work_permit
  doc_tax_card
  doc_health_insurance
  doc_social_security
  doc_employment_contract
)

declare -a CREATED_IDS=()
declare -a CREATED_COUNTRIES=()
declare -a CREATED_TIERS=()

TOTAL_CREATED=0
BATCH_COUNT=0
HTTP_STATUS=
HTTP_BODY=
JSON_ESCAPED=
NAME_FIRST=
NAME_LAST=

rand_from() {
  local -n items=$1
  printf '%s\n' "${items[RANDOM % ${#items[@]}]}"
}

rand_range() {
  printf '%s\n' "$(( $1 + RANDOM % ($2 - $1 + 1) ))"
}

rand_ssn() {
  printf '%03d-%02d-%04d' "$((100 + RANDOM % 900))" "$((10 + RANDOM % 90))" "$((1000 + RANDOM % 9000))"
}

rand_taxid() {
  printf 'DE%09d' "$((100000000 + RANDOM % 900000000))"
}

rand_salary() {
  printf '%s\n' "$(( ($1 + RANDOM % ($2 - $1 + 1)) * 1000 ))"
}

count_file() {
  local file=$1
  [[ -f $file ]] || {
    printf '0\n'
    return
  }
  wc -l < "$file" | tr -d ' '
}

log_line() {
  printf '[%s] %s\n' "$(date '+%H:%M:%S')" "$1" >> "$LOG_FILE"
}

verbose() {
  [[ $VERBOSE == 1 ]] && printf '%s\n' "$1"
}

mark_stat() {
  printf '1\n' >> "$1"
}

show_progress() {
  local current=$1 total=$2 label=$3 pct=100 bar_len=30 filled=30 empty=0 bar=""
  local b

  if (( total > 0 )); then
    pct=$((current * 100 / total))
    filled=$((pct * bar_len / 100))
    empty=$((bar_len - filled))
  fi

  for ((b=0; b<filled; b++)); do bar+="█"; done
  for ((b=0; b<empty; b++)); do bar+="░"; done
  printf '\r  [%s] %d/%d %s (%d%%)' "$bar" "$current" "$total" "$label" "$pct"
}

phase_header() {
  printf '▼ %s\n\n' "$1"
}

queue_job() {
  (
    "$@" || true
  ) &
  BATCH_COUNT=$((BATCH_COUNT + 1))
}

flush_jobs() {
  if (( BATCH_COUNT > 0 )); then
    wait
    BATCH_COUNT=0
  fi
}

wait_for_batch() {
  local current=$1 total=$2 label=$3
  if (( BATCH_COUNT >= PARALLEL )); then
    flush_jobs
    [[ -n $label ]] && show_progress "$current" "$total" "$label"
  fi
}

json_escape() {
  JSON_ESCAPED=${1//\\/\\\\}
  JSON_ESCAPED=${JSON_ESCAPED//\"/\\\"}
  JSON_ESCAPED=${JSON_ESCAPED//$'\n'/\\n}
  JSON_ESCAPED=${JSON_ESCAPED//$'\r'/\\r}
  JSON_ESCAPED=${JSON_ESCAPED//$'\t'/\\t}
}

json_string_pair() {
  json_escape "$2"
  printf '"%s":"%s"' "$1" "$JSON_ESCAPED"
}

json_number_pair() {
  printf '"%s":%s' "$1" "$2"
}

json_null_pair() {
  printf '"%s":null' "$1"
}

build_json() {
  local IFS=,
  printf '{%s}' "$*"
}

json_number_or_default() {
  local json=$1 key=$2 default=${3:-}
  local pattern="\"${key}\"[[:space:]]*:[[:space:]]*([0-9]+([.][0-9]+)?)"

  if [[ $json =~ $pattern ]]; then
    printf '%s\n' "${BASH_REMATCH[1]}"
  else
    printf '%s\n' "$default"
  fi
}

slugify() {
  local value=${1// /-}
  printf '%s\n' "${value,,}"
}

pick_name() {
  local country=$1
  if [[ $country == USA ]]; then
    NAME_FIRST=$(rand_from US_FIRST)
    NAME_LAST=$(rand_from US_LAST)
  else
    NAME_FIRST=$(rand_from DE_FIRST)
    NAME_LAST=$(rand_from DE_LAST)
  fi
}

random_address() {
  printf '%s %s, %s\n' "$(rand_range 100 9999)" "$(rand_from STREETS)" "$(rand_from US_CITIES)"
}

append_base_employee_fields() {
  local -n _ref=$1
  _ref+=(
    "$(json_string_pair name "$2")"
    "$(json_string_pair last_name "$3")"
    "$(json_number_pair salary "$4")"
    "$(json_string_pair country "$5")"
  )
}

append_deu_doc_fields() {
  local -n _ref=$1
  local slug=$2 index=$3 field

  for field in "${DOC_FIELDS[@]}"; do
    if (( RANDOM % 2 == 0 )); then
      _ref+=("$(json_string_pair "$field" "https://hr-docs.example.com/deu/${field}/${slug}-${index}.pdf")")
    fi
  done
}

make_usa_create_payload() {
  local tier=$1 first=$2 last=$3 salary=0 ssn address
  local fields=()

  if [[ $tier != 0 ]]; then
    salary=$(rand_salary 35 180)
  fi

  append_base_employee_fields fields "$first" "$last" "$salary" USA

  case $tier in
    67)
      if (( RANDOM % 2 == 0 )); then
        ssn=$(rand_ssn)
        fields+=("$(json_string_pair ssn "$ssn")")
      else
        address=$(random_address)
        fields+=("$(json_string_pair address "$address")")
      fi
      ;;
    100)
      ssn=$(rand_ssn)
      address=$(random_address)
      fields+=(
        "$(json_string_pair ssn "$ssn")"
        "$(json_string_pair address "$address")"
      )
      ;;
  esac

  build_json "${fields[@]}"
}

make_deu_create_payload() {
  local tier=$1 first=$2 last=$3 index=$4 salary tax_id goal slug
  local fields=()

  if [[ $tier == 0 ]]; then
    salary=0
  else
    salary=$(rand_salary 30 120)
  fi

  tax_id=$(rand_taxid)
  goal=$(rand_from GOALS)
  slug=$(slugify "${first}-${last}")

  append_base_employee_fields fields "$first" "$last" "$salary" DEU
  fields+=(
    "$(json_string_pair tax_id "$tax_id")"
    "$(json_string_pair goal "$goal")"
  )
  append_deu_doc_fields fields "$slug" "$index"

  build_json "${fields[@]}"
}

make_name_update_payload() {
  pick_name "$1"
  build_json \
    "$(json_string_pair name "$NAME_FIRST")" \
    "$(json_string_pair last_name "$NAME_LAST")"
}

make_fill_payload() {
  local country=$1 salary ssn address tax_id goal

  if [[ $country == USA ]]; then
    salary=$(rand_salary 40 180)
    ssn=$(rand_ssn)
    address=$(random_address)
    build_json \
      "$(json_number_pair salary "$salary")" \
      "$(json_string_pair ssn "$ssn")" \
      "$(json_string_pair address "$address")"
    return
  fi

  salary=$(rand_salary 35 120)
  tax_id=$(rand_taxid)
  goal=$(rand_from UPDATED_GOALS)
  build_json \
    "$(json_number_pair salary "$salary")" \
    "$(json_string_pair tax_id "$tax_id")" \
    "$(json_string_pair goal "$goal")"
}

make_full_payload() {
  local country=$1 salary ssn address tax_id goal

  pick_name "$country"
  salary=$(rand_salary 40 200)

  if [[ $country == USA ]]; then
    ssn=$(rand_ssn)
    address=$(random_address)
    build_json \
      "$(json_string_pair name "$NAME_FIRST")" \
      "$(json_string_pair last_name "$NAME_LAST")" \
      "$(json_number_pair salary "$salary")" \
      "$(json_string_pair ssn "$ssn")" \
      "$(json_string_pair address "$address")"
    return
  fi

  tax_id=$(rand_taxid)
  goal=$(rand_from UPDATED_GOALS)
  build_json \
    "$(json_string_pair name "$NAME_FIRST")" \
    "$(json_string_pair last_name "$NAME_LAST")" \
    "$(json_number_pair salary "$salary")" \
    "$(json_string_pair tax_id "$tax_id")" \
    "$(json_string_pair goal "$goal")"
}

make_drop_payload() {
  if [[ $1 == USA ]]; then
    build_json "$(json_null_pair ssn)" "$(json_null_pair address)"
  else
    build_json "$(json_null_pair goal)"
  fi
}

make_deu_downgrade_payload() {
  local tier=$1

  case $tier in
    0)
      build_json "$(json_null_pair tax_id)" "$(json_null_pair goal)"
      ;;
    33)
      build_json "$(json_null_pair tax_id)" "$(json_null_pair goal)"
      ;;
    67)
      if (( RANDOM % 2 == 0 )); then
        build_json "$(json_null_pair tax_id)"
      else
        build_json "$(json_null_pair goal)"
      fi
      ;;
    *)
      return 1
      ;;
  esac
}

curl_status() {
  local url=$1 auth=${2:-}
  local status

  if [[ -n $auth ]]; then
    status=$(curl -sS -u "$auth" -o /dev/null -w '%{http_code}' "$url" 2>/dev/null) || status=000
  else
    status=$(curl -sS -o /dev/null -w '%{http_code}' "$url" 2>/dev/null) || status=000
  fi

  printf '%s\n' "$status"
}

fetch_json() {
  local url=$1 auth=${2:-}

  if [[ -n $auth ]]; then
    curl -fsS -u "$auth" "$url" 2>/dev/null || true
  else
    curl -fsS "$url" 2>/dev/null || true
  fi
}

perform_request() {
  local method=$1 url=$2 payload=${3:-}
  local response
  local -a curl_args=(-sS -X "$method" "$url" -w $'\n%{http_code}')

  if [[ -n $payload ]]; then
    curl_args+=(-H 'Content-Type: application/json' -d "$payload")
  fi

  if ! response=$(curl "${curl_args[@]}" 2>/dev/null); then
    HTTP_STATUS="000"
    HTTP_BODY=
    return 1
  fi

  HTTP_STATUS=${response##*$'\n'}
  if [[ $response == *$'\n'* ]]; then
    HTTP_BODY=${response%$'\n'*}
  else
    HTTP_BODY=
  fi
}

should_retry() {
  [[ $1 == 429 || $1 == 5* || $1 == 000 ]]
}

api_post() {
  local payload=$1 label=$2 country=$3 tier=$4 attempt id body_preview

  for ((attempt=1; attempt<=MAX_RETRIES; attempt++)); do
    perform_request POST "$API" "$payload" || true
    if [[ $HTTP_STATUS == 201 ]]; then
      id=$(json_number_or_default "$HTTP_BODY" id "")
      [[ -n $id ]] && printf '%s %s %s\n' "$id" "$country" "$tier" >> "$CREATED_IDS_FILE"
      mark_stat "$CREATE_OK_FILE"
      verbose "    CREATE $label -> 201 id=$id tier=$tier"
      log_line "CREATE OK $label id=$id tier=$tier"
      return 0
    fi
    should_retry "$HTTP_STATUS" && {
      sleep "$RETRY_WAIT"
      continue
    }
    break
  done

  mark_stat "$CREATE_FAIL_FILE"
  body_preview=${HTTP_BODY//$'\n'/ }
  body_preview=${body_preview:0:200}
  log_line "FAIL CREATE $label status=$HTTP_STATUS body=$body_preview"
  verbose "    FAIL CREATE $label -> $HTTP_STATUS"
  return 1
}

api_put() {
  local id=$1 payload=$2 label=$3 attempt

  for ((attempt=1; attempt<=MAX_RETRIES; attempt++)); do
    perform_request PUT "$API/$id" "$payload" || true
    if [[ $HTTP_STATUS == 200 ]]; then
      mark_stat "$UPDATE_OK_FILE"
      verbose "    UPDATE id=$id $label -> 200"
      log_line "UPDATE OK id=$id $label"
      return 0
    fi
    should_retry "$HTTP_STATUS" && {
      sleep "$RETRY_WAIT"
      continue
    }
    break
  done

  mark_stat "$UPDATE_FAIL_FILE"
  log_line "FAIL UPDATE id=$id $label status=$HTTP_STATUS"
  verbose "    FAIL UPDATE id=$id $label -> $HTTP_STATUS"
  return 1
}

api_delete() {
  local id=$1 attempt

  for ((attempt=1; attempt<=MAX_RETRIES; attempt++)); do
    perform_request DELETE "$API/$id" || true
    if [[ $HTTP_STATUS == 204 || $HTTP_STATUS == 200 || $HTTP_STATUS == 404 ]]; then
      mark_stat "$DELETE_OK_FILE"
      if [[ $HTTP_STATUS != 404 ]]; then
        verbose "    DELETE id=$id -> $HTTP_STATUS"
      fi
      log_line "DELETE OK id=$id status=$HTTP_STATUS"
      return 0
    fi
    should_retry "$HTTP_STATUS" && {
      sleep "$RETRY_WAIT"
      continue
    }
    break
  done

  mark_stat "$DELETE_FAIL_FILE"
  log_line "FAIL DELETE id=$id status=$HTTP_STATUS"
  return 1
}

api_get() {
  local url=$1 label=$2 status
  status=$(curl_status "$url")
  log_line "GET $label -> $status"
  verbose "    GET $label -> $status"
  printf '%s\n' "$status"
}

tier_for_index() {
  local index=$1 total=$2 pct
  pct=$(( index * 100 / total ))

  if (( pct < 10 )); then
    printf '0\n'
  elif (( pct < 25 )); then
    printf '33\n'
  elif (( pct < 50 )); then
    printf '67\n'
  else
    printf '100\n'
  fi
}

print_banner() {
  echo "════════════════════════════════════════════════════════════════"
  echo "  Employee Seed Script"
  echo "  Create → Read → Update → Delete → Verify"
  echo "════════════════════════════════════════════════════════════════"
  echo "  Target:        $TOTAL_USA USA + $TOTAL_DEU DEU = $TOTAL employees"
  echo "  Update:        ~${UPDATE_PCT}% of created employees"
  echo "  Delete:        ~${DELETE_PCT}% of created employees"
  echo "  Checklist:     0%, 33%, 67%, 100% for both countries"
  echo "  HR API:        $API"
  echo "  Hub API:       $HUB_API"
  echo "  Parallelism:   $PARALLEL concurrent requests"
  echo "  Log:           $LOG_FILE"
  echo "════════════════════════════════════════════════════════════════"
  echo
}

check_service() {
  local label=$1 url=$2 required=$3 note=${4:-} auth=${5:-}
  local status

  printf '  Checking %-14s ' "$label"
  status=$(curl_status "$url" "$auth")

  if [[ $status == 200 ]]; then
    printf '✓\n'
    return
  fi

  if (( required == 1 )); then
    printf '✗ (%s)\n' "$status"
    exit 1
  fi

  if [[ -n $note ]]; then
    printf '⚠ (%s) — %s\n' "$status" "$note"
  else
    printf '⚠ (%s)\n' "$status"
  fi
}

preflight() {
  check_service "HR Service" "http://localhost:8001/api/health" 1
  check_service "Hub Service" "http://localhost:8002/api/health" 0 "Hub verify may fail"
  check_service "RabbitMQ" "http://localhost:15672/api/overview" 0 "" "guest:guest"
  echo
}

load_created_records() {
  local id country tier

  CREATED_IDS=()
  CREATED_COUNTRIES=()
  CREATED_TIERS=()

  if [[ -s $CREATED_IDS_FILE ]]; then
    while IFS=' ' read -r id country tier; do
      CREATED_IDS+=("$id")
      CREATED_COUNTRIES+=("$country")
      CREATED_TIERS+=("$tier")
    done < "$CREATED_IDS_FILE"
  fi

  TOTAL_CREATED=${#CREATED_IDS[@]}
}

find_created_id() {
  local country=$1 idx

  for idx in "${!CREATED_IDS[@]}"; do
    if [[ ${CREATED_COUNTRIES[$idx]} == "$country" ]]; then
      printf '%s\n' "${CREATED_IDS[$idx]}"
      return 0
    fi
  done

  return 1
}

create_country_employees() {
  local country=$1 total=$2 index tier payload before_ok before_fail created failed

  if (( total <= 0 )); then
    printf '▶ Creating 0 %s employees\n\n' "$country"
    return
  fi

  before_ok=$(count_file "$CREATE_OK_FILE")
  before_fail=$(count_file "$CREATE_FAIL_FILE")

  if [[ $country == USA ]]; then
    echo "▶ Creating $total USA employees"
    echo "  Distribution: ~10% at 0% | ~15% at 33% | ~25% at 67% | ~50% at 100%"
  else
    echo "▶ Creating $total DEU employees"
    echo "  All created with required fields; partial tiers set via Phase 3 updates"
  fi

  BATCH_COUNT=0
  for ((index=1; index<=total; index++)); do
    pick_name "$country"
    tier=$(tier_for_index "$index" "$total")

    if [[ $country == USA ]]; then
      payload=$(make_usa_create_payload "$tier" "$NAME_FIRST" "$NAME_LAST")
    else
      payload=$(make_deu_create_payload "$tier" "$NAME_FIRST" "$NAME_LAST" "$index")
    fi

    queue_job api_post "$payload" "$country#$index $NAME_FIRST $NAME_LAST" "$country" "$tier"
    wait_for_batch "$index" "$total" "$country"
  done

  flush_jobs
  show_progress "$total" "$total" "$country"
  created=$(( $(count_file "$CREATE_OK_FILE") - before_ok ))
  failed=$(( $(count_file "$CREATE_FAIL_FILE") - before_fail ))
  echo
  echo "  ✓ $country: $created created, $failed failed"
  echo
}

phase_create() {
  phase_header "PHASE 1 — CREATE"
  create_country_employees USA "$TOTAL_USA"
  create_country_employees DEU "$TOTAL_DEU"
  load_created_records
  echo "  Total tracked: $TOTAL_CREATED employees"
  echo
}

read_endpoint() {
  echo "  ▸ $1..."
  api_get "$2" "$3" > /dev/null
}

phase_read() {
  local first_usa_id first_deu_id

  phase_header "PHASE 2 — READ"
  echo "  Waiting ${PROPAGATION_WAIT}s for RabbitMQ event propagation..."
  sleep "$PROPAGATION_WAIT"

  read_endpoint "HR: List employees (page 1)" "$API?page=1&per_page=15" "HR page 1"
  read_endpoint "HR: List employees (page 2)" "$API?page=2&per_page=15" "HR page 2"

  if (( TOTAL_CREATED > 0 )); then
    read_endpoint "HR: Get single employee #${CREATED_IDS[0]}" "$API/${CREATED_IDS[0]}" "HR single"
  fi

  read_endpoint "Hub: USA employees (page 1)" "$HUB_API/employees/USA?page=1&per_page=15" "Hub USA p1"
  read_endpoint "Hub: USA employees (page 2)" "$HUB_API/employees/USA?page=2&per_page=15" "Hub USA p2"
  read_endpoint "Hub: DEU employees (page 1)" "$HUB_API/employees/DEU?page=1&per_page=15" "Hub DEU p1"
  read_endpoint "Hub: DEU employees (page 2)" "$HUB_API/employees/DEU?page=2&per_page=15" "Hub DEU p2"

  first_usa_id=$(find_created_id USA || true)
  first_deu_id=$(find_created_id DEU || true)

  [[ -n $first_usa_id ]] && read_endpoint "Hub: Get USA employee #$first_usa_id" "$HUB_API/employees/USA/$first_usa_id" "Hub USA single"
  [[ -n $first_deu_id ]] && read_endpoint "Hub: Get DEU employee #$first_deu_id" "$HUB_API/employees/DEU/$first_deu_id" "Hub DEU single"

  read_endpoint "Hub: Checklists (USA)" "$HUB_API/checklist/USA" "Hub USA checklist"
  read_endpoint "Hub: Checklists (DEU)" "$HUB_API/checklist/DEU" "Hub DEU checklist"
  read_endpoint "Hub: Checklists (USA page 2)" "$HUB_API/checklist/USA?page=2&per_page=5" "Hub USA checklist p2"
  read_endpoint "Hub: Steps (USA)" "$HUB_API/steps/USA" "Hub USA steps"
  read_endpoint "Hub: Steps (DEU)" "$HUB_API/steps/DEU" "Hub DEU steps"
  read_endpoint "Hub: Schema (USA)" "$HUB_API/schema/USA" "Hub USA schema"
  read_endpoint "Hub: Schema (DEU)" "$HUB_API/schema/DEU" "Hub DEU schema"
  read_endpoint "Error path: HR 404" "$API/999999" "HR 404"
  read_endpoint "Error path: Hub invalid country" "$HUB_API/employees/XYZ" "Hub invalid country"
  read_endpoint "Error path: Hub employee 404" "$HUB_API/employees/USA/999999" "Hub 404"
  read_endpoint "Error path: Hub checklist invalid" "$HUB_API/checklist/XYZ" "Hub checklist invalid"

  echo "  ✓ All read endpoints exercised"
  echo
}

phase_deu_downgrades() {
  local idx employee_id employee_tier payload downgraded=0 label

  echo "▶ Step 3a: Setting DEU checklist tiers via field null-outs..."
  BATCH_COUNT=0

  for idx in "${!CREATED_IDS[@]}"; do
    [[ ${CREATED_COUNTRIES[$idx]} == DEU ]] || continue
    employee_id=${CREATED_IDS[$idx]}
    employee_tier=${CREATED_TIERS[$idx]}

    if ! payload=$(make_deu_downgrade_payload "$employee_tier"); then
      continue
    fi

    label="DEU→${employee_tier}%"
    queue_job api_put "$employee_id" "$payload" "$label"
    downgraded=$((downgraded + 1))
    if (( BATCH_COUNT >= PARALLEL )); then
      flush_jobs
    fi
  done

  flush_jobs
  echo "  ✓ DEU tier downgrades: $downgraded employees modified"
  echo
}

phase_random_updates() {
  local update_count step done=0 idx employee_id employee_country update_type payload label salary

  update_count=$(( TOTAL_CREATED * UPDATE_PCT / 100 ))
  if (( update_count > TOTAL_CREATED )); then
    update_count=$TOTAL_CREATED
  fi

  if (( update_count == 0 || TOTAL_CREATED == 0 )); then
    echo "  (skipped — no employees to update)"
    echo
    return
  fi

  echo "▶ Step 3b: Random updates on $update_count employees (~${UPDATE_PCT}%)"
  echo "  Mix: salary, name, fill-all, full-resubmit, null-out"

  step=$(( TOTAL_CREATED / update_count ))
  (( step < 1 )) && step=1
  BATCH_COUNT=0

  for ((idx=0; idx<TOTAL_CREATED && done<update_count; idx+=step)); do
    employee_id=${CREATED_IDS[$idx]}
    employee_country=${CREATED_COUNTRIES[$idx]}
    done=$((done + 1))
    update_type=$(( done % 5 ))

    case $update_type in
      0)
        salary=$(rand_salary 40 200)
        payload=$(build_json "$(json_number_pair salary "$salary")")
        label="salary→$salary"
        ;;
      1)
        payload=$(make_name_update_payload "$employee_country")
        label="$employee_country rename"
        ;;
      2)
        payload=$(make_fill_payload "$employee_country")
        label="$employee_country fill→100%"
        ;;
      3)
        payload=$(make_full_payload "$employee_country")
        label="$employee_country full-resubmit"
        ;;
      4)
        payload=$(make_drop_payload "$employee_country")
        if [[ $employee_country == USA ]]; then
          label="USA null→33%"
        else
          label="DEU null goal→67%"
        fi
        ;;
    esac

    queue_job api_put "$employee_id" "$payload" "$label"
    wait_for_batch "$done" "$update_count" "UPDATE"
  done

  flush_jobs
  show_progress "$done" "$update_count" "UPDATE"
  echo
  echo "  ✓ Updates: $(count_file "$UPDATE_OK_FILE") succeeded, $(count_file "$UPDATE_FAIL_FILE") failed"
  echo
}

phase_update() {
  phase_header "PHASE 3 — UPDATE"
  phase_deu_downgrades
  phase_random_updates
}

phase_delete() {
  local delete_count start_idx done=0 idx employee_id

  phase_header "PHASE 4 — DELETE"
  delete_count=$(( TOTAL_CREATED * DELETE_PCT / 100 ))
  if (( delete_count > TOTAL_CREATED )); then
    delete_count=$TOTAL_CREATED
  fi

  if (( delete_count == 0 || TOTAL_CREATED == 0 )); then
    echo "  (skipped)"
    echo
    return
  fi

  echo "▶ Deleting $delete_count employees (~${DELETE_PCT}%)"
  start_idx=$(( TOTAL_CREATED - delete_count ))
  (( start_idx < 0 )) && start_idx=0
  BATCH_COUNT=0

  for ((idx=start_idx; idx<TOTAL_CREATED && done<delete_count; idx++)); do
    employee_id=${CREATED_IDS[$idx]}
    done=$((done + 1))
    queue_job api_delete "$employee_id"
    wait_for_batch "$done" "$delete_count" "DELETE"
  done

  flush_jobs
  show_progress "$done" "$delete_count" "DELETE"
  echo
  echo "  ✓ Deletes: $(count_file "$DELETE_OK_FILE") succeeded, $(count_file "$DELETE_FAIL_FILE") failed"
  echo
}

phase_verify() {
  local hr_json hub_usa_json hub_deu_json checklist_json queue_json dlq_json
  local country total complete pct

  phase_header "PHASE 5 — VERIFY"
  echo "  Waiting ${PROPAGATION_WAIT}s for final event propagation..."
  sleep "$PROPAGATION_WAIT"

  echo
  echo "  ── HR Service ──"
  hr_json=$(fetch_json "$API?per_page=1")
  echo "  Total employees: $(json_number_or_default "$hr_json" total '?')"

  echo
  echo "  ── Hub Service ──"
  hub_usa_json=$(fetch_json "$HUB_API/employees/USA?per_page=1")
  hub_deu_json=$(fetch_json "$HUB_API/employees/DEU?per_page=1")
  echo "  USA projected: $(json_number_or_default "$hub_usa_json" total '?')"
  echo "  DEU projected: $(json_number_or_default "$hub_deu_json" total '?')"

  echo
  echo "  ── Checklists ──"
  for country in USA DEU; do
    checklist_json=$(fetch_json "$HUB_API/checklist/$country?per_page=999")
    total=$(json_number_or_default "$checklist_json" total_employees '?')
    complete=$(json_number_or_default "$checklist_json" complete_employees '?')
    pct=$(json_number_or_default "$checklist_json" overall_percentage '?')
    echo "  $country: $total employees, $complete fully complete, overall ${pct}%"
  done

  echo
  echo "  ── RabbitMQ ──"
  queue_json=$(fetch_json "http://localhost:15672/api/queues/%2f/hub_employee_events" "guest:guest")
  dlq_json=$(fetch_json "http://localhost:15672/api/queues/%2f/hub_employee_events_dlq" "guest:guest")
  echo "  Main queue: $(json_number_or_default "$queue_json" messages '?') pending"
  echo "  DLQ: $(json_number_or_default "$dlq_json" messages '?') messages"
}

print_summary() {
  local end_time elapsed total_create_ok total_create_fail total_update_ok total_update_fail total_delete_ok total_delete_fail total_ops rps

  end_time=$(date +%s)
  elapsed=$(( end_time - START_TIME ))
  total_create_ok=$(count_file "$CREATE_OK_FILE")
  total_create_fail=$(count_file "$CREATE_FAIL_FILE")
  total_update_ok=$(count_file "$UPDATE_OK_FILE")
  total_update_fail=$(count_file "$UPDATE_FAIL_FILE")
  total_delete_ok=$(count_file "$DELETE_OK_FILE")
  total_delete_fail=$(count_file "$DELETE_FAIL_FILE")
  total_ops=$(( total_create_ok + total_create_fail + total_update_ok + total_update_fail + total_delete_ok + total_delete_fail ))
  rps=$(( total_ops / (elapsed + 1) ))

  echo
  echo "════════════════════════════════════════════════════════════════"
  echo "  SUMMARY"
  echo "────────────────────────────────────────────────────────────────"
  echo "  CREATE   $total_create_ok ok / $total_create_fail failed"
  echo "  UPDATE   $total_update_ok ok / $total_update_fail failed"
  echo "  DELETE   $total_delete_ok ok / $total_delete_fail failed"
  echo "────────────────────────────────────────────────────────────────"
  echo "  Total ops:   $total_ops"
  echo "  Duration:    ${elapsed}s (~${rps} req/s)"
  echo "  Log:         $LOG_FILE"
  echo "════════════════════════════════════════════════════════════════"
  echo
}

main() {
  START_TIME=$(date +%s)
  print_banner
  preflight
  phase_create
  phase_read
  phase_update
  phase_delete
  phase_verify
  print_summary
}

main "$@"