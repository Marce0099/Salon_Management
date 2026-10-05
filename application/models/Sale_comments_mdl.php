<?php if( !defined('BASEPATH') ) exit();

class Sale_comments_mdl extends CI_Model{

    public function __construct(){

        parent::__construct();
    }

    public function saleExists( $id_sale ){

        return $this->db->where( 'id_sale', $id_sale )->count_all_results( 'sales' ) > 0;
    }

    public function getBySale( $id_sale ){

        return $this->db->query(
            'select SC.id_sale_comment, SC.id_admin, SC.comment, SC.created_at, A.name as author
            from sale_comments SC left outer join admins A on SC.id_admin = A.id_admin
            where SC.id_sale = ? order by SC.created_at asc, SC.id_sale_comment asc',
            array( (int) $id_sale )
        )->result();
    }

    public function getOne( $id_sale_comment ){

        return $this->db->query(
            'select SC.id_sale_comment, SC.id_admin, SC.comment, SC.created_at, A.name as author
            from sale_comments SC left outer join admins A on SC.id_admin = A.id_admin
            where SC.id_sale_comment = ?',
            array( (int) $id_sale_comment )
        )->row();
    }

    // Returns the new id, or FALSE when the insert failed.
    public function add( $id_sale, $id_admin, $comment ){

        $ok = $this->db->insert( 'sale_comments', array(
            'id_sale'  => (int) $id_sale,
            'id_admin' => (int) $id_admin,
            'comment'  => $comment,
        ));

        return $ok ? $this->db->insert_id() : FALSE;
    }
}
