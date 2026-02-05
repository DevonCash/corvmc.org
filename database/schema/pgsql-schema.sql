--
-- PostgreSQL database dump
--

-- Dumped from database version 17.5 (Postgres.app)
-- Dumped by pg_dump version 17.5 (Postgres.app)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: postgis; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;


--
-- Name: EXTENSION postgis; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION postgis IS 'PostGIS geometry and geography spatial types and functions';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: activity_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_log (
    id bigint NOT NULL,
    log_name character varying(255),
    description text NOT NULL,
    subject_type character varying(255),
    subject_id bigint,
    causer_type character varying(255),
    causer_id bigint,
    properties json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    event character varying(255),
    batch_uuid uuid
);


--
-- Name: activity_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activity_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activity_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activity_log_id_seq OWNED BY public.activity_log.id;


--
-- Name: band_profile_members; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.band_profile_members (
    id bigint NOT NULL,
    band_profile_id bigint NOT NULL,
    user_id bigint NOT NULL,
    invited_at timestamp(0) without time zone,
    role character varying(255) DEFAULT 'member'::character varying NOT NULL,
    "position" character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    CONSTRAINT band_profile_members_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'invited'::character varying])::text[])))
);


--
-- Name: band_profile_members_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.band_profile_members_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: band_profile_members_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.band_profile_members_id_seq OWNED BY public.band_profile_members.id;


--
-- Name: band_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.band_profiles (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    hometown character varying(255),
    owner_id bigint,
    name character varying(255) NOT NULL,
    bio text,
    links json,
    contact json,
    visibility character varying(255) DEFAULT 'private'::character varying NOT NULL,
    embeds json,
    slug character varying(255),
    status character varying(255) DEFAULT 'active'::character varying NOT NULL
);


--
-- Name: band_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.band_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: band_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.band_profiles_id_seq OWNED BY public.band_profiles.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: charges; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.charges (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    chargeable_type character varying(255) NOT NULL,
    chargeable_id bigint NOT NULL,
    amount bigint NOT NULL,
    credits_applied json,
    net_amount bigint NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    payment_method character varying(255),
    paid_at timestamp(0) without time zone,
    stripe_session_id character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: charges_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.charges_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: charges_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.charges_id_seq OWNED BY public.charges.id;


--
-- Name: credit_allocations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.credit_allocations (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    credit_type character varying(50) NOT NULL,
    amount integer NOT NULL,
    frequency character varying(20) NOT NULL,
    source character varying(100) NOT NULL,
    source_id character varying(255),
    starts_at timestamp(0) without time zone NOT NULL,
    ends_at timestamp(0) without time zone,
    last_allocated_at timestamp(0) without time zone,
    next_allocation_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: credit_allocations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.credit_allocations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: credit_allocations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.credit_allocations_id_seq OWNED BY public.credit_allocations.id;


--
-- Name: credit_transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.credit_transactions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    credit_type character varying(50) NOT NULL,
    amount integer NOT NULL,
    balance_after integer NOT NULL,
    source character varying(100) NOT NULL,
    source_id bigint,
    description text,
    metadata json,
    created_at timestamp(0) without time zone NOT NULL
);


--
-- Name: credit_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.credit_transactions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: credit_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.credit_transactions_id_seq OWNED BY public.credit_transactions.id;


--
-- Name: equipment; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(255) NOT NULL,
    brand character varying(255),
    model character varying(255),
    serial_number character varying(255),
    description text,
    condition character varying(255) NOT NULL,
    acquisition_type character varying(255) NOT NULL,
    provider_id bigint,
    provider_contact json,
    acquisition_date date NOT NULL,
    return_due_date date,
    acquisition_notes text,
    ownership_status character varying(255) DEFAULT 'cmc_owned'::character varying NOT NULL,
    status character varying(255) DEFAULT 'available'::character varying NOT NULL,
    estimated_value numeric(8,2),
    location character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    parent_equipment_id bigint,
    can_lend_separately boolean DEFAULT true NOT NULL,
    is_kit boolean DEFAULT false NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    state character varying(255) DEFAULT 'available'::character varying NOT NULL,
    loanable boolean DEFAULT true NOT NULL,
    CONSTRAINT equipment_acquisition_type_check CHECK (((acquisition_type)::text = ANY ((ARRAY['donated'::character varying, 'loaned_to_us'::character varying, 'purchased'::character varying])::text[]))),
    CONSTRAINT equipment_condition_check CHECK (((condition)::text = ANY ((ARRAY['excellent'::character varying, 'good'::character varying, 'fair'::character varying, 'poor'::character varying, 'needs_repair'::character varying])::text[]))),
    CONSTRAINT equipment_ownership_status_check CHECK (((ownership_status)::text = ANY ((ARRAY['cmc_owned'::character varying, 'on_loan_to_cmc'::character varying, 'returned_to_owner'::character varying])::text[]))),
    CONSTRAINT equipment_status_check CHECK (((status)::text = ANY ((ARRAY['available'::character varying, 'checked_out'::character varying, 'maintenance'::character varying, 'retired'::character varying])::text[]))),
    CONSTRAINT equipment_type_check CHECK (((type)::text = ANY ((ARRAY['guitar'::character varying, 'bass'::character varying, 'amplifier'::character varying, 'microphone'::character varying, 'percussion'::character varying, 'recording'::character varying, 'specialty'::character varying])::text[])))
);


--
-- Name: equipment_damage_reports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_damage_reports (
    id bigint NOT NULL,
    equipment_id bigint NOT NULL,
    equipment_loan_id bigint,
    reported_by_id bigint NOT NULL,
    assigned_to_id bigint,
    title character varying(255) NOT NULL,
    description text NOT NULL,
    severity character varying(255) DEFAULT 'medium'::character varying NOT NULL,
    status character varying(255) DEFAULT 'reported'::character varying NOT NULL,
    priority character varying(255) DEFAULT 'normal'::character varying NOT NULL,
    repair_notes text,
    discovered_at timestamp(0) without time zone NOT NULL,
    started_at timestamp(0) without time zone,
    completed_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    estimated_cost integer,
    actual_cost integer
);


--
-- Name: equipment_damage_reports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipment_damage_reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipment_damage_reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipment_damage_reports_id_seq OWNED BY public.equipment_damage_reports.id;


--
-- Name: equipment_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipment_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipment_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipment_id_seq OWNED BY public.equipment.id;


--
-- Name: equipment_loans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_loans (
    id bigint NOT NULL,
    equipment_id bigint NOT NULL,
    borrower_id bigint NOT NULL,
    checked_out_at timestamp(0) without time zone,
    due_at timestamp(0) without time zone NOT NULL,
    returned_at timestamp(0) without time zone,
    condition_in character varying(255),
    security_deposit numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    rental_fee numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    notes text,
    damage_notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    state character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    reserved_from timestamp(0) without time zone NOT NULL,
    condition_out character varying(255),
    CONSTRAINT equipment_loans_condition_in_check CHECK (((condition_in)::text = ANY ((ARRAY['excellent'::character varying, 'good'::character varying, 'fair'::character varying, 'poor'::character varying, 'needs_repair'::character varying])::text[]))),
    CONSTRAINT equipment_loans_condition_out_check CHECK (((condition_out)::text = ANY ((ARRAY['excellent'::character varying, 'good'::character varying, 'fair'::character varying, 'poor'::character varying, 'needs_repair'::character varying])::text[])))
);


--
-- Name: equipment_loans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipment_loans_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipment_loans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipment_loans_id_seq OWNED BY public.equipment_loans.id;


--
-- Name: event_bands; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_bands (
    id bigint NOT NULL,
    "order" integer DEFAULT 0 NOT NULL,
    set_length integer,
    event_id bigint NOT NULL,
    band_profile_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: event_bands_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_bands_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_bands_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_bands_id_seq OWNED BY public.event_bands.id;


--
-- Name: events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.events (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    title character varying(255),
    description text,
    start_datetime timestamp(0) without time zone NOT NULL,
    end_datetime timestamp(0) without time zone,
    doors_datetime timestamp(0) without time zone,
    location json,
    event_link character varying(255),
    ticket_url character varying(255),
    ticket_price numeric(8,2),
    published_at timestamp(0) without time zone,
    organizer_id bigint,
    status character varying(255) DEFAULT 'approved'::character varying NOT NULL,
    visibility character varying(255) DEFAULT 'public'::character varying NOT NULL,
    event_type character varying(255),
    distance_from_corvallis numeric(5,2),
    trust_points integer DEFAULT 0 NOT NULL,
    auto_approved boolean DEFAULT false NOT NULL,
    recurring_series_id bigint,
    instance_date date,
    rescheduled_to_id bigint,
    reschedule_reason text,
    venue_id bigint,
    cancellation_reason character varying(255),
    ticketing_enabled boolean DEFAULT false NOT NULL,
    ticket_quantity integer,
    ticket_price_override bigint,
    tickets_sold integer DEFAULT 0 NOT NULL
);


--
-- Name: COLUMN events.ticket_quantity; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.events.ticket_quantity IS 'Total tickets available, null = unlimited';


--
-- Name: COLUMN events.ticket_price_override; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.events.ticket_price_override IS 'Override global price in cents';


--
-- Name: COLUMN events.tickets_sold; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.events.tickets_sold IS 'Cached count of sold tickets';


--
-- Name: events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.events_id_seq OWNED BY public.events.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: flags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.flags (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    flaggable_type character varying(255) NOT NULL,
    flaggable_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: flags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.flags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: flags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.flags_id_seq OWNED BY public.flags.id;


--
-- Name: invitations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.invitations (
    id bigint NOT NULL,
    inviter_id bigint,
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    last_sent_at timestamp(0) without time zone,
    used_at timestamp(0) without time zone,
    message character varying(255),
    data jsonb,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: invitations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.invitations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: invitations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.invitations_id_seq OWNED BY public.invitations.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: kiosk_devices; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kiosk_devices (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    api_key character varying(64) NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    has_tap_to_pay boolean DEFAULT false NOT NULL,
    payment_device_id bigint,
    last_seen_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: kiosk_devices_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kiosk_devices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kiosk_devices_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kiosk_devices_id_seq OWNED BY public.kiosk_devices.id;


--
-- Name: kiosk_payment_requests; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.kiosk_payment_requests (
    id bigint NOT NULL,
    source_device_id bigint NOT NULL,
    target_device_id bigint NOT NULL,
    event_id bigint NOT NULL,
    amount integer NOT NULL,
    quantity integer NOT NULL,
    customer_email character varying(255),
    is_sustaining_member boolean DEFAULT false NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    payment_intent_id character varying(255),
    failure_reason character varying(255),
    expires_at timestamp(0) without time zone NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: kiosk_payment_requests_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.kiosk_payment_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: kiosk_payment_requests_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.kiosk_payment_requests_id_seq OWNED BY public.kiosk_payment_requests.id;


--
-- Name: local_resources; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.local_resources (
    id bigint NOT NULL,
    resource_list_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    contact_name character varying(255),
    contact_email character varying(255),
    contact_phone character varying(255),
    website character varying(255),
    address character varying(255),
    published_at timestamp(0) without time zone,
    sort_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: local_resources_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.local_resources_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: local_resources_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.local_resources_id_seq OWNED BY public.local_resources.id;


--
-- Name: media; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.media (
    id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL,
    uuid uuid,
    collection_name character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    file_name character varying(255) NOT NULL,
    mime_type character varying(255),
    disk character varying(255) NOT NULL,
    conversions_disk character varying(255),
    size bigint NOT NULL,
    manipulations json NOT NULL,
    custom_properties json NOT NULL,
    generated_conversions json NOT NULL,
    responsive_images json NOT NULL,
    order_column integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: media_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.media_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: media_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.media_id_seq OWNED BY public.media.id;


--
-- Name: member_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.member_profiles (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    user_id bigint NOT NULL,
    hometown character varying(255),
    bio text,
    links json,
    contact json,
    visibility character varying(255) DEFAULT 'private'::character varying NOT NULL,
    embeds json
);


--
-- Name: member_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.member_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: member_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.member_profiles_id_seq OWNED BY public.member_profiles.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: model_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_permissions (
    permission_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: model_has_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.model_has_roles (
    role_id bigint NOT NULL,
    model_type character varying(255) NOT NULL,
    model_id bigint NOT NULL
);


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data jsonb NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permissions_id_seq OWNED BY public.permissions.id;


--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name text NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: recurring_series; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.recurring_series (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    recurrence_rule character varying(255) NOT NULL,
    start_time time(0) without time zone NOT NULL,
    end_time time(0) without time zone NOT NULL,
    series_start_date date NOT NULL,
    series_end_date date,
    max_advance_days integer DEFAULT 90 NOT NULL,
    status character varying(20) DEFAULT 'active'::character varying NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    recurable_type character varying(255) DEFAULT 'App\Models\Reservation'::character varying NOT NULL
);


--
-- Name: recurring_reservations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.recurring_reservations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: recurring_reservations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.recurring_reservations_id_seq OWNED BY public.recurring_series.id;


--
-- Name: reports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reports (
    id bigint NOT NULL,
    reportable_type character varying(255) NOT NULL,
    reportable_id bigint NOT NULL,
    reported_by_id bigint NOT NULL,
    reason character varying(255) NOT NULL,
    custom_reason text,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    resolved_by_id bigint,
    resolved_at timestamp(0) without time zone,
    resolution_notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT reports_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'upheld'::character varying, 'dismissed'::character varying, 'escalated'::character varying])::text[])))
);


--
-- Name: reports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reports_id_seq OWNED BY public.reports.id;


--
-- Name: reservation_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reservation_users (
    reservation_id bigint NOT NULL,
    user_id bigint NOT NULL
);


--
-- Name: reservations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reservations (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    hours_used numeric(4,2) DEFAULT '0'::numeric NOT NULL,
    free_hours_used numeric(4,2) DEFAULT '0'::numeric NOT NULL,
    is_recurring boolean DEFAULT false NOT NULL,
    recurrence_pattern json,
    notes text,
    reserved_at timestamp(0) without time zone,
    reserved_until timestamp(0) without time zone,
    recurring_series_id bigint,
    instance_date date,
    cancellation_reason character varying(100),
    type character varying(255) DEFAULT 'App\Models\RehearsalReservation'::character varying NOT NULL,
    reservable_type character varying(255),
    reservable_id bigint,
    google_calendar_event_id character varying(255)
);


--
-- Name: reservations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reservations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reservations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reservations_id_seq OWNED BY public.reservations.id;


--
-- Name: resource_lists; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.resource_lists (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    published_at timestamp(0) without time zone,
    display_order integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: resource_lists_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.resource_lists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: resource_lists_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.resource_lists_id_seq OWNED BY public.resource_lists.id;


--
-- Name: revisions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.revisions (
    id bigint NOT NULL,
    revisionable_type character varying(255) NOT NULL,
    revisionable_id bigint NOT NULL,
    original_data json NOT NULL,
    proposed_changes json NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    submitted_by_id bigint NOT NULL,
    reviewed_by_id bigint,
    reviewed_at timestamp(0) without time zone,
    review_reason text,
    revision_type character varying(255) DEFAULT 'update'::character varying NOT NULL,
    auto_approved boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: revisions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.revisions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: revisions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.revisions_id_seq OWNED BY public.revisions.id;


--
-- Name: role_has_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.role_has_permissions (
    permission_id bigint NOT NULL,
    role_id bigint NOT NULL
);


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    guard_name character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: settings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.settings (
    id bigint NOT NULL,
    "group" character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    locked boolean DEFAULT false NOT NULL,
    payload json NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: settings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.settings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: settings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.settings_id_seq OWNED BY public.settings.id;


--
-- Name: space_closures; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.space_closures (
    id bigint NOT NULL,
    starts_at timestamp(0) without time zone NOT NULL,
    ends_at timestamp(0) without time zone NOT NULL,
    reason character varying(255),
    type character varying(255) DEFAULT 'other'::character varying NOT NULL,
    notes text,
    created_by_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: space_closures_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.space_closures_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: space_closures_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.space_closures_id_seq OWNED BY public.space_closures.id;


--
-- Name: sponsor_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sponsor_user (
    id bigint NOT NULL,
    sponsor_id bigint NOT NULL,
    user_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: sponsor_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sponsor_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sponsor_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sponsor_user_id_seq OWNED BY public.sponsor_user.id;


--
-- Name: sponsors; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sponsors (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    tier character varying(255) NOT NULL,
    type character varying(255) DEFAULT 'cash'::character varying NOT NULL,
    description text,
    website_url character varying(255),
    logo_path character varying(255),
    display_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    started_at date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: sponsors_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sponsors_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sponsors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sponsors_id_seq OWNED BY public.sponsors.id;


--
-- Name: staff_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.staff_profiles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    user_id bigint NOT NULL,
    title character varying(255),
    bio text,
    type character varying(255) NOT NULL,
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    email character varying(255),
    social_links json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT staff_profiles_type_check CHECK (((type)::text = ANY ((ARRAY['board'::character varying, 'staff'::character varying])::text[])))
);


--
-- Name: staff_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.staff_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: staff_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.staff_profiles_id_seq OWNED BY public.staff_profiles.id;


--
-- Name: subscription_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscription_items (
    id bigint NOT NULL,
    subscription_id bigint NOT NULL,
    stripe_id character varying(255) NOT NULL,
    stripe_product character varying(255) NOT NULL,
    stripe_price character varying(255) NOT NULL,
    quantity integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: subscription_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscription_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscription_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscription_items_id_seq OWNED BY public.subscription_items.id;


--
-- Name: subscriptions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.subscriptions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    type character varying(255) NOT NULL,
    stripe_id character varying(255) NOT NULL,
    stripe_status character varying(255) NOT NULL,
    stripe_price character varying(255),
    quantity integer,
    trial_ends_at timestamp(0) without time zone,
    ends_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    base_amount integer,
    total_amount integer,
    currency character varying(3) DEFAULT 'USD'::character varying NOT NULL,
    covers_fees boolean DEFAULT false NOT NULL,
    metadata json
);


--
-- Name: subscriptions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: subscriptions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.subscriptions_id_seq OWNED BY public.subscriptions.id;


--
-- Name: taggables; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.taggables (
    tag_id bigint NOT NULL,
    taggable_type character varying(255) NOT NULL,
    taggable_id bigint NOT NULL
);


--
-- Name: tags; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tags (
    id bigint NOT NULL,
    name json NOT NULL,
    slug json NOT NULL,
    type character varying(255),
    order_column integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: tags_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tags_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tags_id_seq OWNED BY public.tags.id;


--
-- Name: ticket_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ticket_orders (
    id bigint NOT NULL,
    uuid uuid NOT NULL,
    user_id bigint,
    event_id bigint NOT NULL,
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    email character varying(255),
    name character varying(255),
    quantity integer NOT NULL,
    unit_price bigint NOT NULL,
    subtotal bigint NOT NULL,
    discount bigint DEFAULT '0'::bigint NOT NULL,
    fees bigint DEFAULT '0'::bigint NOT NULL,
    total bigint NOT NULL,
    covers_fees boolean DEFAULT false NOT NULL,
    is_door_sale boolean DEFAULT false NOT NULL,
    payment_method character varying(255),
    completed_at timestamp(0) without time zone,
    refunded_at timestamp(0) without time zone,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN ticket_orders.unit_price; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.ticket_orders.unit_price IS 'Price per ticket at purchase time in cents';


--
-- Name: COLUMN ticket_orders.subtotal; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.ticket_orders.subtotal IS 'quantity × unit_price in cents';


--
-- Name: COLUMN ticket_orders.discount; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.ticket_orders.discount IS 'Member discount in cents';


--
-- Name: COLUMN ticket_orders.fees; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.ticket_orders.fees IS 'Stripe fees if covered in cents';


--
-- Name: COLUMN ticket_orders.total; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.ticket_orders.total IS 'Final amount charged in cents';


--
-- Name: COLUMN ticket_orders.payment_method; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.ticket_orders.payment_method IS 'stripe, cash, card, comp';


--
-- Name: ticket_orders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.ticket_orders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: ticket_orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.ticket_orders_id_seq OWNED BY public.ticket_orders.id;


--
-- Name: tickets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tickets (
    id bigint NOT NULL,
    ticket_order_id bigint NOT NULL,
    code character varying(32) NOT NULL,
    attendee_name character varying(255),
    attendee_email character varying(255),
    status character varying(255) DEFAULT 'valid'::character varying NOT NULL,
    checked_in_at timestamp(0) without time zone,
    checked_in_by bigint,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: tickets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.tickets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: tickets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.tickets_id_seq OWNED BY public.tickets.id;


--
-- Name: trust_achievements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trust_achievements (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    content_type character varying(100) NOT NULL,
    level character varying(20) NOT NULL,
    achieved_at timestamp(0) without time zone NOT NULL
);


--
-- Name: trust_achievements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trust_achievements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trust_achievements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trust_achievements_id_seq OWNED BY public.trust_achievements.id;


--
-- Name: trust_transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.trust_transactions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    content_type character varying(100) NOT NULL,
    points integer NOT NULL,
    balance_after integer NOT NULL,
    reason character varying(255) NOT NULL,
    source_type character varying(50) NOT NULL,
    source_id bigint,
    awarded_by_id bigint,
    metadata json,
    created_at timestamp(0) without time zone NOT NULL
);


--
-- Name: trust_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.trust_transactions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: trust_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.trust_transactions_id_seq OWNED BY public.trust_transactions.id;


--
-- Name: user_credits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_credits (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    credit_type character varying(50) DEFAULT 'free_hours'::character varying NOT NULL,
    balance integer DEFAULT 0 NOT NULL,
    max_balance integer,
    rollover_enabled boolean DEFAULT false NOT NULL,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_credits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_credits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_credits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_credits_id_seq OWNED BY public.user_credits.id;


--
-- Name: user_trust_balances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.user_trust_balances (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    content_type character varying(100) DEFAULT 'global'::character varying NOT NULL,
    balance integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: user_trust_balances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.user_trust_balances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: user_trust_balances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.user_trust_balances_id_seq OWNED BY public.user_trust_balances.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    pronouns character varying(255),
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    stripe_id character varying(255),
    pm_type character varying(255),
    pm_last_four character varying(4),
    trial_ends_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    settings json,
    phone character varying(255)
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: venues; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.venues (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    address character varying(255) NOT NULL,
    city character varying(255) DEFAULT 'Corvallis'::character varying NOT NULL,
    state character varying(2) DEFAULT 'OR'::character varying NOT NULL,
    zip character varying(10),
    latitude numeric(10,7),
    longitude numeric(10,7),
    distance_from_corvallis integer,
    distance_cached_at timestamp(0) without time zone,
    is_cmc boolean DEFAULT false NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: COLUMN venues.distance_from_corvallis; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.venues.distance_from_corvallis IS 'Driving time in minutes';


--
-- Name: venues_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.venues_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: venues_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.venues_id_seq OWNED BY public.venues.id;


--
-- Name: activity_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log ALTER COLUMN id SET DEFAULT nextval('public.activity_log_id_seq'::regclass);


--
-- Name: band_profile_members id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.band_profile_members ALTER COLUMN id SET DEFAULT nextval('public.band_profile_members_id_seq'::regclass);


--
-- Name: band_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.band_profiles ALTER COLUMN id SET DEFAULT nextval('public.band_profiles_id_seq'::regclass);


--
-- Name: charges id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.charges ALTER COLUMN id SET DEFAULT nextval('public.charges_id_seq'::regclass);


--
-- Name: credit_allocations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_allocations ALTER COLUMN id SET DEFAULT nextval('public.credit_allocations_id_seq'::regclass);


--
-- Name: credit_transactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_transactions ALTER COLUMN id SET DEFAULT nextval('public.credit_transactions_id_seq'::regclass);


--
-- Name: equipment id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment ALTER COLUMN id SET DEFAULT nextval('public.equipment_id_seq'::regclass);


--
-- Name: equipment_damage_reports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_damage_reports ALTER COLUMN id SET DEFAULT nextval('public.equipment_damage_reports_id_seq'::regclass);


--
-- Name: equipment_loans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_loans ALTER COLUMN id SET DEFAULT nextval('public.equipment_loans_id_seq'::regclass);


--
-- Name: event_bands id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_bands ALTER COLUMN id SET DEFAULT nextval('public.event_bands_id_seq'::regclass);


--
-- Name: events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events ALTER COLUMN id SET DEFAULT nextval('public.events_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: flags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flags ALTER COLUMN id SET DEFAULT nextval('public.flags_id_seq'::regclass);


--
-- Name: invitations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations ALTER COLUMN id SET DEFAULT nextval('public.invitations_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: kiosk_devices id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiosk_devices ALTER COLUMN id SET DEFAULT nextval('public.kiosk_devices_id_seq'::regclass);


--
-- Name: kiosk_payment_requests id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiosk_payment_requests ALTER COLUMN id SET DEFAULT nextval('public.kiosk_payment_requests_id_seq'::regclass);


--
-- Name: local_resources id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.local_resources ALTER COLUMN id SET DEFAULT nextval('public.local_resources_id_seq'::regclass);


--
-- Name: media id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media ALTER COLUMN id SET DEFAULT nextval('public.media_id_seq'::regclass);


--
-- Name: member_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.member_profiles ALTER COLUMN id SET DEFAULT nextval('public.member_profiles_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permissions_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: recurring_series id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_series ALTER COLUMN id SET DEFAULT nextval('public.recurring_reservations_id_seq'::regclass);


--
-- Name: reports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reports ALTER COLUMN id SET DEFAULT nextval('public.reports_id_seq'::regclass);


--
-- Name: reservations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservations ALTER COLUMN id SET DEFAULT nextval('public.reservations_id_seq'::regclass);


--
-- Name: resource_lists id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resource_lists ALTER COLUMN id SET DEFAULT nextval('public.resource_lists_id_seq'::regclass);


--
-- Name: revisions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.revisions ALTER COLUMN id SET DEFAULT nextval('public.revisions_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: settings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.settings ALTER COLUMN id SET DEFAULT nextval('public.settings_id_seq'::regclass);


--
-- Name: space_closures id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.space_closures ALTER COLUMN id SET DEFAULT nextval('public.space_closures_id_seq'::regclass);


--
-- Name: sponsor_user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sponsor_user ALTER COLUMN id SET DEFAULT nextval('public.sponsor_user_id_seq'::regclass);


--
-- Name: sponsors id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sponsors ALTER COLUMN id SET DEFAULT nextval('public.sponsors_id_seq'::regclass);


--
-- Name: staff_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_profiles ALTER COLUMN id SET DEFAULT nextval('public.staff_profiles_id_seq'::regclass);


--
-- Name: subscription_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_items ALTER COLUMN id SET DEFAULT nextval('public.subscription_items_id_seq'::regclass);


--
-- Name: subscriptions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions ALTER COLUMN id SET DEFAULT nextval('public.subscriptions_id_seq'::regclass);


--
-- Name: tags id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags ALTER COLUMN id SET DEFAULT nextval('public.tags_id_seq'::regclass);


--
-- Name: ticket_orders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_orders ALTER COLUMN id SET DEFAULT nextval('public.ticket_orders_id_seq'::regclass);


--
-- Name: tickets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets ALTER COLUMN id SET DEFAULT nextval('public.tickets_id_seq'::regclass);


--
-- Name: trust_achievements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_achievements ALTER COLUMN id SET DEFAULT nextval('public.trust_achievements_id_seq'::regclass);


--
-- Name: trust_transactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_transactions ALTER COLUMN id SET DEFAULT nextval('public.trust_transactions_id_seq'::regclass);


--
-- Name: user_credits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_credits ALTER COLUMN id SET DEFAULT nextval('public.user_credits_id_seq'::regclass);


--
-- Name: user_trust_balances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_trust_balances ALTER COLUMN id SET DEFAULT nextval('public.user_trust_balances_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: venues id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venues ALTER COLUMN id SET DEFAULT nextval('public.venues_id_seq'::regclass);


--
-- Name: activity_log activity_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log
    ADD CONSTRAINT activity_log_pkey PRIMARY KEY (id);


--
-- Name: band_profile_members band_profile_members_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.band_profile_members
    ADD CONSTRAINT band_profile_members_pkey PRIMARY KEY (id);


--
-- Name: band_profile_members band_profile_members_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.band_profile_members
    ADD CONSTRAINT band_profile_members_unique UNIQUE (band_profile_id, user_id);


--
-- Name: band_profiles band_profiles_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.band_profiles
    ADD CONSTRAINT band_profiles_name_unique UNIQUE (name);


--
-- Name: band_profiles band_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.band_profiles
    ADD CONSTRAINT band_profiles_pkey PRIMARY KEY (id);


--
-- Name: band_profiles band_profiles_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.band_profiles
    ADD CONSTRAINT band_profiles_slug_unique UNIQUE (slug);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: charges charges_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.charges
    ADD CONSTRAINT charges_pkey PRIMARY KEY (id);


--
-- Name: credit_allocations credit_allocations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_allocations
    ADD CONSTRAINT credit_allocations_pkey PRIMARY KEY (id);


--
-- Name: credit_transactions credit_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_transactions
    ADD CONSTRAINT credit_transactions_pkey PRIMARY KEY (id);


--
-- Name: equipment_damage_reports equipment_damage_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_damage_reports
    ADD CONSTRAINT equipment_damage_reports_pkey PRIMARY KEY (id);


--
-- Name: equipment_loans equipment_loans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_loans
    ADD CONSTRAINT equipment_loans_pkey PRIMARY KEY (id);


--
-- Name: equipment equipment_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_pkey PRIMARY KEY (id);


--
-- Name: event_bands event_bands_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_bands
    ADD CONSTRAINT event_bands_pkey PRIMARY KEY (id);


--
-- Name: events events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: flags flags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flags
    ADD CONSTRAINT flags_pkey PRIMARY KEY (id);


--
-- Name: invitations invitations_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_email_unique UNIQUE (email);


--
-- Name: invitations invitations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_pkey PRIMARY KEY (id);


--
-- Name: invitations invitations_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_token_unique UNIQUE (token);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: kiosk_devices kiosk_devices_api_key_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiosk_devices
    ADD CONSTRAINT kiosk_devices_api_key_unique UNIQUE (api_key);


--
-- Name: kiosk_devices kiosk_devices_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiosk_devices
    ADD CONSTRAINT kiosk_devices_pkey PRIMARY KEY (id);


--
-- Name: kiosk_payment_requests kiosk_payment_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiosk_payment_requests
    ADD CONSTRAINT kiosk_payment_requests_pkey PRIMARY KEY (id);


--
-- Name: local_resources local_resources_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.local_resources
    ADD CONSTRAINT local_resources_pkey PRIMARY KEY (id);


--
-- Name: media media_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_pkey PRIMARY KEY (id);


--
-- Name: media media_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.media
    ADD CONSTRAINT media_uuid_unique UNIQUE (uuid);


--
-- Name: member_profiles member_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.member_profiles
    ADD CONSTRAINT member_profiles_pkey PRIMARY KEY (id);


--
-- Name: member_profiles member_profiles_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.member_profiles
    ADD CONSTRAINT member_profiles_user_id_unique UNIQUE (user_id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: model_has_permissions model_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_pkey PRIMARY KEY (permission_id, model_id, model_type);


--
-- Name: model_has_roles model_has_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_pkey PRIMARY KEY (role_id, model_id, model_type);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: permissions permissions_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: recurring_series recurring_reservations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_series
    ADD CONSTRAINT recurring_reservations_pkey PRIMARY KEY (id);


--
-- Name: reports reports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reports
    ADD CONSTRAINT reports_pkey PRIMARY KEY (id);


--
-- Name: reservations reservations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservations
    ADD CONSTRAINT reservations_pkey PRIMARY KEY (id);


--
-- Name: resource_lists resource_lists_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resource_lists
    ADD CONSTRAINT resource_lists_pkey PRIMARY KEY (id);


--
-- Name: resource_lists resource_lists_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.resource_lists
    ADD CONSTRAINT resource_lists_slug_unique UNIQUE (slug);


--
-- Name: revisions revisions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.revisions
    ADD CONSTRAINT revisions_pkey PRIMARY KEY (id);


--
-- Name: role_has_permissions role_has_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_pkey PRIMARY KEY (permission_id, role_id);


--
-- Name: roles roles_name_guard_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_guard_name_unique UNIQUE (name, guard_name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: settings settings_group_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_group_name_unique UNIQUE ("group", name);


--
-- Name: settings settings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_pkey PRIMARY KEY (id);


--
-- Name: space_closures space_closures_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.space_closures
    ADD CONSTRAINT space_closures_pkey PRIMARY KEY (id);


--
-- Name: sponsor_user sponsor_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sponsor_user
    ADD CONSTRAINT sponsor_user_pkey PRIMARY KEY (id);


--
-- Name: sponsor_user sponsor_user_sponsor_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sponsor_user
    ADD CONSTRAINT sponsor_user_sponsor_id_user_id_unique UNIQUE (sponsor_id, user_id);


--
-- Name: sponsors sponsors_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sponsors
    ADD CONSTRAINT sponsors_pkey PRIMARY KEY (id);


--
-- Name: staff_profiles staff_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_profiles
    ADD CONSTRAINT staff_profiles_pkey PRIMARY KEY (id);


--
-- Name: subscription_items subscription_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_items
    ADD CONSTRAINT subscription_items_pkey PRIMARY KEY (id);


--
-- Name: subscription_items subscription_items_stripe_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscription_items
    ADD CONSTRAINT subscription_items_stripe_id_unique UNIQUE (stripe_id);


--
-- Name: subscriptions subscriptions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);


--
-- Name: subscriptions subscriptions_stripe_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_stripe_id_unique UNIQUE (stripe_id);


--
-- Name: taggables taggables_tag_id_taggable_id_taggable_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.taggables
    ADD CONSTRAINT taggables_tag_id_taggable_id_taggable_type_unique UNIQUE (tag_id, taggable_id, taggable_type);


--
-- Name: tags tags_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tags
    ADD CONSTRAINT tags_pkey PRIMARY KEY (id);


--
-- Name: ticket_orders ticket_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_orders
    ADD CONSTRAINT ticket_orders_pkey PRIMARY KEY (id);


--
-- Name: ticket_orders ticket_orders_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_orders
    ADD CONSTRAINT ticket_orders_uuid_unique UNIQUE (uuid);


--
-- Name: tickets tickets_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_code_unique UNIQUE (code);


--
-- Name: tickets tickets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_pkey PRIMARY KEY (id);


--
-- Name: trust_achievements trust_achievements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_achievements
    ADD CONSTRAINT trust_achievements_pkey PRIMARY KEY (id);


--
-- Name: trust_achievements trust_achievements_user_id_content_type_level_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_achievements
    ADD CONSTRAINT trust_achievements_user_id_content_type_level_unique UNIQUE (user_id, content_type, level);


--
-- Name: trust_transactions trust_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_transactions
    ADD CONSTRAINT trust_transactions_pkey PRIMARY KEY (id);


--
-- Name: reports unique_user_content_report; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reports
    ADD CONSTRAINT unique_user_content_report UNIQUE (reportable_type, reportable_id, reported_by_id);


--
-- Name: user_credits user_credits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_credits
    ADD CONSTRAINT user_credits_pkey PRIMARY KEY (id);


--
-- Name: user_credits user_credits_user_id_credit_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_credits
    ADD CONSTRAINT user_credits_user_id_credit_type_unique UNIQUE (user_id, credit_type);


--
-- Name: user_trust_balances user_trust_balances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_trust_balances
    ADD CONSTRAINT user_trust_balances_pkey PRIMARY KEY (id);


--
-- Name: user_trust_balances user_trust_balances_user_id_content_type_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_trust_balances
    ADD CONSTRAINT user_trust_balances_user_id_content_type_unique UNIQUE (user_id, content_type);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: venues venues_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.venues
    ADD CONSTRAINT venues_pkey PRIMARY KEY (id);


--
-- Name: activity_log_log_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_log_name_index ON public.activity_log USING btree (log_name);


--
-- Name: band_profiles_slug_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX band_profiles_slug_index ON public.band_profiles USING btree (slug);


--
-- Name: causer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX causer ON public.activity_log USING btree (causer_type, causer_id);


--
-- Name: charges_chargeable_type_chargeable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX charges_chargeable_type_chargeable_id_index ON public.charges USING btree (chargeable_type, chargeable_id);


--
-- Name: charges_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX charges_status_index ON public.charges USING btree (status);


--
-- Name: credit_allocations_next_allocation_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX credit_allocations_next_allocation_at_index ON public.credit_allocations USING btree (next_allocation_at);


--
-- Name: credit_allocations_user_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX credit_allocations_user_id_is_active_index ON public.credit_allocations USING btree (user_id, is_active);


--
-- Name: credit_transactions_source_source_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX credit_transactions_source_source_id_index ON public.credit_transactions USING btree (source, source_id);


--
-- Name: credit_transactions_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX credit_transactions_user_id_created_at_index ON public.credit_transactions USING btree (user_id, created_at);


--
-- Name: equipment_acquisition_type_provider_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_acquisition_type_provider_id_index ON public.equipment USING btree (acquisition_type, provider_id);


--
-- Name: equipment_damage_reports_assigned_to_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_damage_reports_assigned_to_id_status_index ON public.equipment_damage_reports USING btree (assigned_to_id, status);


--
-- Name: equipment_damage_reports_equipment_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_damage_reports_equipment_id_status_index ON public.equipment_damage_reports USING btree (equipment_id, status);


--
-- Name: equipment_damage_reports_severity_priority_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_damage_reports_severity_priority_index ON public.equipment_damage_reports USING btree (severity, priority);


--
-- Name: equipment_loans_borrower_id_state_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_loans_borrower_id_state_index ON public.equipment_loans USING btree (borrower_id, state);


--
-- Name: equipment_loans_due_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_loans_due_at_index ON public.equipment_loans USING btree (due_at);


--
-- Name: equipment_loans_equipment_id_reserved_from_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_loans_equipment_id_reserved_from_index ON public.equipment_loans USING btree (equipment_id, reserved_from);


--
-- Name: equipment_loans_equipment_id_state_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_loans_equipment_id_state_index ON public.equipment_loans USING btree (equipment_id, state);


--
-- Name: equipment_loans_reserved_from_due_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_loans_reserved_from_due_at_index ON public.equipment_loans USING btree (reserved_from, due_at);


--
-- Name: equipment_loans_state_due_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_loans_state_due_at_index ON public.equipment_loans USING btree (state, due_at);


--
-- Name: equipment_ownership_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_ownership_status_index ON public.equipment USING btree (ownership_status);


--
-- Name: equipment_type_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_type_status_index ON public.equipment USING btree (type, status);


--
-- Name: events_instance_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_instance_date_index ON public.events USING btree (instance_date);


--
-- Name: events_organizer_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_organizer_id_index ON public.events USING btree (organizer_id);


--
-- Name: events_recurring_series_id_instance_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_recurring_series_id_instance_date_index ON public.events USING btree (recurring_series_id, instance_date);


--
-- Name: events_status_start_time_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_status_start_time_index ON public.events USING btree (status, start_datetime);


--
-- Name: events_venue_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_venue_id_index ON public.events USING btree (venue_id);


--
-- Name: events_visibility_published_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_visibility_published_at_index ON public.events USING btree (visibility, published_at);


--
-- Name: flags_flaggable_type_flaggable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX flags_flaggable_type_flaggable_id_index ON public.flags USING btree (flaggable_type, flaggable_id);


--
-- Name: flags_name_flaggable_id_flaggable_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX flags_name_flaggable_id_flaggable_type_index ON public.flags USING btree (name, flaggable_id, flaggable_type);


--
-- Name: idx_band_members_lookup; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_band_members_lookup ON public.band_profile_members USING btree (user_id, band_profile_id);


--
-- Name: idx_member_profiles_user_visibility; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_member_profiles_user_visibility ON public.member_profiles USING btree (user_id, visibility);


--
-- Name: idx_member_profiles_visibility; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_member_profiles_visibility ON public.member_profiles USING btree (visibility);


--
-- Name: idx_model_has_roles_lookup; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_model_has_roles_lookup ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: idx_reservations_conflict_detection; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_reservations_conflict_detection ON public.reservations USING btree (reserved_at, reserved_until, status);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: kiosk_payment_requests_source_device_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX kiosk_payment_requests_source_device_id_status_index ON public.kiosk_payment_requests USING btree (source_device_id, status);


--
-- Name: kiosk_payment_requests_target_device_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX kiosk_payment_requests_target_device_id_status_index ON public.kiosk_payment_requests USING btree (target_device_id, status);


--
-- Name: media_model_type_model_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_model_type_model_id_index ON public.media USING btree (model_type, model_id);


--
-- Name: media_order_column_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX media_order_column_index ON public.media USING btree (order_column);


--
-- Name: model_has_permissions_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_permissions_model_id_model_type_index ON public.model_has_permissions USING btree (model_id, model_type);


--
-- Name: model_has_roles_model_id_model_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX model_has_roles_model_id_model_type_index ON public.model_has_roles USING btree (model_id, model_type);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: personal_access_tokens_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_expires_at_index ON public.personal_access_tokens USING btree (expires_at);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: recurring_reservations_status_series_end_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX recurring_reservations_status_series_end_date_index ON public.recurring_series USING btree (status, series_end_date);


--
-- Name: recurring_reservations_user_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX recurring_reservations_user_id_status_index ON public.recurring_series USING btree (user_id, status);


--
-- Name: recurring_series_recurable_type_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX recurring_series_recurable_type_status_index ON public.recurring_series USING btree (recurable_type, status);


--
-- Name: reports_reportable_type_reportable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reports_reportable_type_reportable_id_index ON public.reports USING btree (reportable_type, reportable_id);


--
-- Name: reservations_instance_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reservations_instance_date_index ON public.reservations USING btree (instance_date);


--
-- Name: reservations_recurring_series_id_instance_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reservations_recurring_series_id_instance_date_index ON public.reservations USING btree (recurring_series_id, instance_date);


--
-- Name: reservations_reservable_type_reservable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reservations_reservable_type_reservable_id_index ON public.reservations USING btree (reservable_type, reservable_id);


--
-- Name: reservations_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reservations_type_index ON public.reservations USING btree (type);


--
-- Name: revisions_revisionable_type_revisionable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX revisions_revisionable_type_revisionable_id_index ON public.revisions USING btree (revisionable_type, revisionable_id);


--
-- Name: revisions_status_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX revisions_status_created_at_index ON public.revisions USING btree (status, created_at);


--
-- Name: revisions_submitted_by_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX revisions_submitted_by_id_status_index ON public.revisions USING btree (submitted_by_id, status);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: space_closures_starts_at_ends_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX space_closures_starts_at_ends_at_index ON public.space_closures USING btree (starts_at, ends_at);


--
-- Name: space_closures_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX space_closures_type_index ON public.space_closures USING btree (type);


--
-- Name: sponsor_user_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sponsor_user_user_id_index ON public.sponsor_user USING btree (user_id);


--
-- Name: sponsors_tier_is_active_display_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sponsors_tier_is_active_display_order_index ON public.sponsors USING btree (tier, is_active, display_order);


--
-- Name: subject; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subject ON public.activity_log USING btree (subject_type, subject_id);


--
-- Name: subscription_items_subscription_id_stripe_price_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscription_items_subscription_id_stripe_price_index ON public.subscription_items USING btree (subscription_id, stripe_price);


--
-- Name: subscriptions_base_amount_currency_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_base_amount_currency_index ON public.subscriptions USING btree (base_amount, currency);


--
-- Name: subscriptions_user_id_stripe_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subscriptions_user_id_stripe_status_index ON public.subscriptions USING btree (user_id, stripe_status);


--
-- Name: taggables_taggable_type_taggable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX taggables_taggable_type_taggable_id_index ON public.taggables USING btree (taggable_type, taggable_id);


--
-- Name: ticket_orders_email_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_orders_email_index ON public.ticket_orders USING btree (email);


--
-- Name: ticket_orders_event_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_orders_event_id_status_index ON public.ticket_orders USING btree (event_id, status);


--
-- Name: ticket_orders_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX ticket_orders_status_index ON public.ticket_orders USING btree (status);


--
-- Name: tickets_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_status_index ON public.tickets USING btree (status);


--
-- Name: tickets_ticket_order_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX tickets_ticket_order_id_status_index ON public.tickets USING btree (ticket_order_id, status);


--
-- Name: trust_achievements_achieved_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trust_achievements_achieved_at_index ON public.trust_achievements USING btree (achieved_at);


--
-- Name: trust_achievements_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trust_achievements_user_id_index ON public.trust_achievements USING btree (user_id);


--
-- Name: trust_transactions_content_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trust_transactions_content_type_index ON public.trust_transactions USING btree (content_type);


--
-- Name: trust_transactions_source_type_source_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trust_transactions_source_type_source_id_index ON public.trust_transactions USING btree (source_type, source_id);


--
-- Name: trust_transactions_user_id_created_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX trust_transactions_user_id_created_at_index ON public.trust_transactions USING btree (user_id, created_at);


--
-- Name: user_trust_balances_content_type_balance_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_trust_balances_content_type_balance_index ON public.user_trust_balances USING btree (content_type, balance);


--
-- Name: user_trust_balances_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX user_trust_balances_user_id_index ON public.user_trust_balances USING btree (user_id);


--
-- Name: users_stripe_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_stripe_id_index ON public.users USING btree (stripe_id);


--
-- Name: venues_is_cmc_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX venues_is_cmc_index ON public.venues USING btree (is_cmc);


--
-- Name: venues_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX venues_name_index ON public.venues USING btree (name);


--
-- Name: band_profile_members band_profile_members_band_profile_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.band_profile_members
    ADD CONSTRAINT band_profile_members_band_profile_id_foreign FOREIGN KEY (band_profile_id) REFERENCES public.band_profiles(id) ON DELETE CASCADE;


--
-- Name: band_profile_members band_profile_members_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.band_profile_members
    ADD CONSTRAINT band_profile_members_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: band_profiles band_profiles_owner_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.band_profiles
    ADD CONSTRAINT band_profiles_owner_id_foreign FOREIGN KEY (owner_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: charges charges_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.charges
    ADD CONSTRAINT charges_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: credit_allocations credit_allocations_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_allocations
    ADD CONSTRAINT credit_allocations_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: credit_transactions credit_transactions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_transactions
    ADD CONSTRAINT credit_transactions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: equipment_damage_reports equipment_damage_reports_assigned_to_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_damage_reports
    ADD CONSTRAINT equipment_damage_reports_assigned_to_id_foreign FOREIGN KEY (assigned_to_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment_damage_reports equipment_damage_reports_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_damage_reports
    ADD CONSTRAINT equipment_damage_reports_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment_damage_reports equipment_damage_reports_equipment_loan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_damage_reports
    ADD CONSTRAINT equipment_damage_reports_equipment_loan_id_foreign FOREIGN KEY (equipment_loan_id) REFERENCES public.equipment_loans(id) ON DELETE SET NULL;


--
-- Name: equipment_damage_reports equipment_damage_reports_reported_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_damage_reports
    ADD CONSTRAINT equipment_damage_reports_reported_by_id_foreign FOREIGN KEY (reported_by_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: equipment_loans equipment_loans_borrower_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_loans
    ADD CONSTRAINT equipment_loans_borrower_id_foreign FOREIGN KEY (borrower_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: equipment_loans equipment_loans_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_loans
    ADD CONSTRAINT equipment_loans_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment equipment_parent_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_parent_equipment_id_foreign FOREIGN KEY (parent_equipment_id) REFERENCES public.equipment(id) ON DELETE CASCADE;


--
-- Name: equipment equipment_provider_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment
    ADD CONSTRAINT equipment_provider_id_foreign FOREIGN KEY (provider_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: event_bands event_bands_band_profile_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_bands
    ADD CONSTRAINT event_bands_band_profile_id_foreign FOREIGN KEY (band_profile_id) REFERENCES public.band_profiles(id) ON DELETE CASCADE;


--
-- Name: event_bands event_bands_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_bands
    ADD CONSTRAINT event_bands_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: events events_organizer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_organizer_id_foreign FOREIGN KEY (organizer_id) REFERENCES public.users(id);


--
-- Name: events events_recurring_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_recurring_series_id_foreign FOREIGN KEY (recurring_series_id) REFERENCES public.recurring_series(id) ON DELETE SET NULL;


--
-- Name: events events_rescheduled_to_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_rescheduled_to_id_foreign FOREIGN KEY (rescheduled_to_id) REFERENCES public.events(id) ON DELETE SET NULL;


--
-- Name: events events_venue_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_venue_id_foreign FOREIGN KEY (venue_id) REFERENCES public.venues(id);


--
-- Name: invitations invitations_inviter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.invitations
    ADD CONSTRAINT invitations_inviter_id_foreign FOREIGN KEY (inviter_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: kiosk_devices kiosk_devices_payment_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiosk_devices
    ADD CONSTRAINT kiosk_devices_payment_device_id_foreign FOREIGN KEY (payment_device_id) REFERENCES public.kiosk_devices(id) ON DELETE SET NULL;


--
-- Name: kiosk_payment_requests kiosk_payment_requests_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiosk_payment_requests
    ADD CONSTRAINT kiosk_payment_requests_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: kiosk_payment_requests kiosk_payment_requests_source_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiosk_payment_requests
    ADD CONSTRAINT kiosk_payment_requests_source_device_id_foreign FOREIGN KEY (source_device_id) REFERENCES public.kiosk_devices(id) ON DELETE CASCADE;


--
-- Name: kiosk_payment_requests kiosk_payment_requests_target_device_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.kiosk_payment_requests
    ADD CONSTRAINT kiosk_payment_requests_target_device_id_foreign FOREIGN KEY (target_device_id) REFERENCES public.kiosk_devices(id) ON DELETE CASCADE;


--
-- Name: local_resources local_resources_resource_list_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.local_resources
    ADD CONSTRAINT local_resources_resource_list_id_foreign FOREIGN KEY (resource_list_id) REFERENCES public.resource_lists(id) ON DELETE CASCADE;


--
-- Name: member_profiles member_profiles_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.member_profiles
    ADD CONSTRAINT member_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: model_has_permissions model_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_permissions
    ADD CONSTRAINT model_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: model_has_roles model_has_roles_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.model_has_roles
    ADD CONSTRAINT model_has_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: recurring_series recurring_reservations_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.recurring_series
    ADD CONSTRAINT recurring_reservations_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: reports reports_reported_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reports
    ADD CONSTRAINT reports_reported_by_id_foreign FOREIGN KEY (reported_by_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: reports reports_resolved_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reports
    ADD CONSTRAINT reports_resolved_by_id_foreign FOREIGN KEY (resolved_by_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: reservation_users reservation_users_reservation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservation_users
    ADD CONSTRAINT reservation_users_reservation_id_foreign FOREIGN KEY (reservation_id) REFERENCES public.reservations(id) ON DELETE CASCADE;


--
-- Name: reservation_users reservation_users_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservation_users
    ADD CONSTRAINT reservation_users_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: reservations reservations_recurring_series_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reservations
    ADD CONSTRAINT reservations_recurring_series_id_foreign FOREIGN KEY (recurring_series_id) REFERENCES public.recurring_series(id) ON DELETE SET NULL;


--
-- Name: revisions revisions_reviewed_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.revisions
    ADD CONSTRAINT revisions_reviewed_by_id_foreign FOREIGN KEY (reviewed_by_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: revisions revisions_submitted_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.revisions
    ADD CONSTRAINT revisions_submitted_by_id_foreign FOREIGN KEY (submitted_by_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_permission_id_foreign FOREIGN KEY (permission_id) REFERENCES public.permissions(id) ON DELETE CASCADE;


--
-- Name: role_has_permissions role_has_permissions_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.role_has_permissions
    ADD CONSTRAINT role_has_permissions_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE CASCADE;


--
-- Name: space_closures space_closures_created_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.space_closures
    ADD CONSTRAINT space_closures_created_by_id_foreign FOREIGN KEY (created_by_id) REFERENCES public.users(id);


--
-- Name: sponsor_user sponsor_user_sponsor_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sponsor_user
    ADD CONSTRAINT sponsor_user_sponsor_id_foreign FOREIGN KEY (sponsor_id) REFERENCES public.sponsors(id) ON DELETE CASCADE;


--
-- Name: sponsor_user sponsor_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sponsor_user
    ADD CONSTRAINT sponsor_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: staff_profiles staff_profiles_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.staff_profiles
    ADD CONSTRAINT staff_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: taggables taggables_tag_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.taggables
    ADD CONSTRAINT taggables_tag_id_foreign FOREIGN KEY (tag_id) REFERENCES public.tags(id) ON DELETE CASCADE;


--
-- Name: ticket_orders ticket_orders_event_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_orders
    ADD CONSTRAINT ticket_orders_event_id_foreign FOREIGN KEY (event_id) REFERENCES public.events(id) ON DELETE CASCADE;


--
-- Name: ticket_orders ticket_orders_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ticket_orders
    ADD CONSTRAINT ticket_orders_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tickets tickets_checked_in_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_checked_in_by_foreign FOREIGN KEY (checked_in_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: tickets tickets_ticket_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tickets
    ADD CONSTRAINT tickets_ticket_order_id_foreign FOREIGN KEY (ticket_order_id) REFERENCES public.ticket_orders(id) ON DELETE CASCADE;


--
-- Name: trust_achievements trust_achievements_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_achievements
    ADD CONSTRAINT trust_achievements_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: trust_transactions trust_transactions_awarded_by_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_transactions
    ADD CONSTRAINT trust_transactions_awarded_by_id_foreign FOREIGN KEY (awarded_by_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: trust_transactions trust_transactions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.trust_transactions
    ADD CONSTRAINT trust_transactions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_credits user_credits_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_credits
    ADD CONSTRAINT user_credits_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: user_trust_balances user_trust_balances_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.user_trust_balances
    ADD CONSTRAINT user_trust_balances_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

--
-- PostgreSQL database dump
--

-- Dumped from database version 17.5 (Postgres.app)
-- Dumped by pg_dump version 17.5 (Postgres.app)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2022_12_14_083707_create_settings_table	1
5	2025_07_30_214503_create_permission_tables	1
6	2025_07_30_214503_create_tag_tables	1
7	2025_07_30_215017_create_media_table	1
8	2025_07_30_223210_create_band_profiles_table	1
9	2025_07_30_224143_create_reservations_table	1
10	2025_07_30_224154_create_productions_tables	1
11	2025_07_30_224204_create_member_profiles_table	1
12	2025_07_31_005215_create_flags_table	1
13	2025_07_31_015044_create_member_directory_settings	1
14	2025_07_31_200845_create_notifications_table	1
15	2025_08_09_013534_create_organization_settings	1
16	2025_08_09_230747_create_staff_profiles_table	1
17	2025_08_10_064952_footer_settings	1
18	2025_08_10_194825_create_activity_log_table	1
19	2025_08_10_194826_add_event_column_to_activity_log_table	1
20	2025_08_10_194827_add_batch_uuid_column_to_activity_log_table	1
21	2025_08_16_211535_add_embeds_to_profiles_tables	1
22	2025_08_26_004505_add_show_on_about_page_to_users_table	1
23	2025_08_29_191205_create_customer_columns	1
24	2025_08_29_191206_create_subscriptions_table	1
25	2025_08_29_191207_create_subscription_items_table	1
26	2025_08_29_191311_add_stripe_payment_fields_to_reservations_table	1
27	2025_09_04_022609_add_slug_to_band_profiles_table	1
28	2025_09_04_193139_add_performance_indexes	1
29	2025_09_04_201846_create_reports_table_v2	1
30	2025_09_10_191238_add_status_to_band_profiles_table	1
31	2025_09_12_010619_create_invitations_table	1
32	2025_09_12_172552_add_soft_deletes_to_users_table	1
33	2025_09_15_005311_create_equipment_table	1
34	2025_09_15_010743_create_equipment_loans_table	1
35	2025_09_15_194513_add_parent_child_to_equipment_table	1
36	2025_09_15_202057_add_state_to_equipment_table	1
37	2025_09_15_202203_add_state_to_equipment_loans_table	1
38	2025_09_15_203141_create_equipment_damage_reports_table	1
39	2025_09_15_205458_change_cost_columns_to_integers_in_equipment_damage_reports	1
40	2025_09_15_210117_make_checked_out_at_nullable_in_equipment_loans_table	1
41	2025_09_15_213742_remove_status_column_from_equipment_loans_table	1
42	2025_09_15_214023_fix_equipment_loans_table_indexes	1
43	2025_09_16_010515_add_reservation_start_time_to_equipment_loans_table	1
44	2025_09_16_012435_make_condition_out_nullable_in_equipment_loans_table	1
45	2025_09_16_015459_add_loanable_field_to_equipment_table	1
46	2025_09_16_071252_add_equipment_settings	1
47	2025_09_16_181259_create_community_events_table	1
48	2025_09_16_181320_add_trust_points_to_users_table	1
49	2025_09_16_213932_add_trust_points_to_users_table	1
50	2025_09_16_214718_create_revisions_table	1
51	2025_09_18_200506_convert_money_columns_to_cents	1
52	2025_09_20_190500_add_dynamic_pricing_fields_to_subscriptions_table	1
53	2025_09_22_183417_make_inviter_id_nullable_on_invitations_table	1
54	2025_09_24_020859_community_calendar_settings	1
55	2025_09_24_020903_equipment_settings	1
56	2025_10_01_120000_create_trust_system_tables	1
57	2025_10_01_121000_remove_old_trust_columns	1
58	2025_10_01_181822_create_user_credits_table	1
59	2025_10_01_181828_create_credit_transactions_table	1
60	2025_10_01_181829_create_credit_allocations_table	1
61	2025_10_01_194056_create_recurring_reservations_table	1
62	2025_10_01_194120_add_recurring_fields_to_reservations_table	1
63	2025_10_02_215840_create_sponsors_table	1
64	2025_10_03_003622_create_bylaws_settings	1
65	2025_10_03_193925_add_sti_and_polymorphic_to_reservations_table	1
66	2025_10_11_000000_fix_reservation_types	1
67	2025_11_04_000000_create_google_calendar_settings	1
68	2025_11_04_185030_add_google_calendar_event_id_to_reservations_table	1
69	2025_11_12_124843_simplify_band_members_cmc_only	1
70	2025_11_12_140148_create_events_table	1
71	2025_11_12_143711_migrate_productions_to_events	1
72	2025_11_12_143826_drop_productions_and_community_events_tables	1
73	2025_11_12_144900_fix_event_sequences	1
74	2025_11_13_130739_make_events_title_nullable	1
75	2025_11_13_132233_make_recurring_system_polymorphic	1
76	2025_11_17_194744_cleanup_community_event_references	1
77	2025_11_20_114619_add_moderation_status_to_events_table	1
78	2025_11_20_120211_update_events_status_to_use_enum	1
79	2025_11_20_120711_add_rescheduled_to_events	1
80	2025_12_09_122401_rename_event_time_columns_to_datetime	1
81	2025_12_10_154924_remove_moderation_fields_from_events_table	1
82	2025_12_10_160806_create_venues_table	1
83	2025_12_10_170530_add_venue_id_to_events_table	1
84	2025_12_10_170610_migrate_event_location_data_to_venues	1
85	2025_12_10_190351_remove_duration_column_from_recurring_series	1
86	2025_12_15_154354_create_sponsor_user_table	1
87	2026_01_10_174753_remove_submission_reason_from_revisions	1
88	2026_01_19_000000_create_reservation_settings	1
89	2026_01_21_153400_update_events_with_cancellation_reason	1
90	2026_01_21_171117_create_charges_table	1
91	2026_01_21_172553_migrate_reservation_payments_to_charges	1
92	2026_01_25_134112_remove_legacy_payment_fields_from_reservations	1
93	2026_01_25_173438_update_morph_types_to_aliases	1
94	2026_01_26_000001_create_resource_lists_table	1
95	2026_01_26_000002_create_local_resources_table	1
96	2026_01_27_100000_add_ticketing_fields_to_events_table	1
97	2026_01_27_100001_create_ticket_orders_table	1
98	2026_01_27_100002_create_tickets_table	1
99	2026_01_27_190257_add_unique_constraint_to_band_profile_members	1
100	2026_01_28_122738_add_settings_to_users_table	1
101	2026_01_28_125025_add_phone_to_users_table	1
102	2026_01_29_000000_add_event_setup_teardown_defaults	1
103	2026_01_29_000001_create_space_closures_table	1
104	2026_01_29_140752_create_personal_access_tokens_table	1
105	2026_01_29_150000_create_kiosk_devices_table	1
106	2026_01_29_150001_create_kiosk_payment_requests_table	1
107	2026_01_29_215323_make_space_closure_reason_nullable	1
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 107, true);


--
-- PostgreSQL database dump complete
--

